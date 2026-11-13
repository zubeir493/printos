<?php

use App\Filament\Imports\AttendanceSegmentImporter;
use App\Filament\Pages\AttendanceDevice;
use App\Filament\Resources\AttendanceSegments\AttendanceSegmentResource;
use App\Filament\Resources\AttendanceSegments\Pages\ManageAttendanceSegments;
use App\Jobs\SyncDeviceAttendance as SyncJob;
use App\Models\AttendanceDailySummary;
use App\Models\AttendanceDevicePunch;
use App\Models\AttendanceDeviceSync;
use App\Models\AttendanceLog;
use App\Models\AttendanceSegment;
use App\Models\Employee;
use App\Models\EmployeeShiftOverride;
use App\Models\EmployeeWorkAssignment;
use App\Models\PayrollRun;
use App\Models\PayrollRunEmployee;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\Hr\ImportAttendanceSegmentCsv;
use App\Services\Hr\ReadZktecoAttendance;
use App\Services\Hr\SyncDeviceAttendance;
use App\UserRole;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Models\Import;
use Filament\Facades\Filament;
use HafizAsifAli\ZkTeco\DeviceConfig;
use HafizAsifAli\ZkTeco\Protocol\DeviceClock;
use HafizAsifAli\ZkTeco\Protocol\Opcode;
use HafizAsifAli\ZkTeco\Transport\Transport;
use HafizAsifAli\ZkTeco\ZkDevice;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['attendance_device.enabled' => true, 'attendance_device.processing_enabled' => true, 'attendance_device.pairing' => 'device', 'attendance_device.sync_from' => null]);
    $this->travelTo(now()->setDate(2026, 11, 10)->setTime(12, 0));
    $this->shift = Shift::create([
        'name' => 'Normal', 'start_time' => '08:00:00', 'end_time' => '17:00:00',
        'expected_minutes' => 480, 'break_minutes' => 60, 'break_start' => '12:00:00', 'break_end' => '13:00:00', 'grace_minutes' => 5, 'overtime_eligible' => true,
    ]);
    $this->employee = Employee::factory()->create(['attendance_device_id' => '12']);
    $schedule = WorkSchedule::create(['name' => 'Device test week']);
    foreach (range(0, 6) as $weekday) {
        $schedule->days()->create(['day_of_week' => $weekday, 'shift_id' => $this->shift->id]);
    }
    $this->assignment = EmployeeWorkAssignment::factory()->create(['employee_id' => $this->employee->id, 'work_schedule_id' => $schedule->id]);
});

function deviceAttendanceRow(string $time, int $direction, string $userId = '12', int $uid = 99): array
{
    return ['user_id' => $userId, 'uid' => $uid, 'timestamp' => $time, 'raw_direction' => $direction, 'raw_verify' => 1];
}

function mockDeviceAttendance(array $rows): void
{
    $reader = Mockery::mock(ReadZktecoAttendance::class);
    $reader->shouldReceive('handle')->andReturn($rows);
    app()->instance(ReadZktecoAttendance::class, $reader);
}

it('matches employee number rather than device slot and reuses daily summary calculations idempotently', function (): void {
    mockDeviceAttendance([
        deviceAttendanceRow('2026-11-09 08:00:00', 0),
        deviceAttendanceRow('2026-11-09 08:00:00', 0),
        deviceAttendanceRow('2026-11-09 18:00:00', 1),
    ]);
    app(SyncDeviceAttendance::class)->handle();
    $updatedAt = AttendanceSegment::firstOrFail()->updated_at;
    $this->travel(5)->minutes();
    app(SyncDeviceAttendance::class)->handle();

    expect(AttendanceDevicePunch::count())->toBe(2)
        ->and(AttendanceLog::count())->toBe(2)
        ->and(AttendanceSegment::count())->toBe(1)
        ->and(AttendanceSegment::firstOrFail()->employee_id)->toBe($this->employee->id)
        ->and(AttendanceSegment::firstOrFail()->updated_at->eq($updatedAt))->toBeTrue()
        ->and(AttendanceDailySummary::firstOrFail()->worked_minutes)->toBe(480)
        ->and(AttendanceDailySummary::firstOrFail()->overtime_minutes)->toBe(60)
        ->and(AttendanceDeviceSync::firstOrFail()->last_successful_sync_at)->not->toBeNull();
});

it('recovers old retained punches after an outage without advancing the successful sync time on failure', function (): void {
    mockDeviceAttendance([deviceAttendanceRow('2026-11-09 08:00:00', 0), deviceAttendanceRow('2026-11-09 17:00:00', 1)]);
    app(SyncDeviceAttendance::class)->handle();
    $lastSuccess = AttendanceDeviceSync::firstOrFail()->last_successful_sync_at;
    $this->travel(5)->minutes();
    $reader = Mockery::mock(ReadZktecoAttendance::class);
    $reader->shouldReceive('handle')->andThrow(new RuntimeException('Device unreachable'));
    app()->instance(ReadZktecoAttendance::class, $reader);
    expect(fn () => app(SyncDeviceAttendance::class)->handle())->toThrow(RuntimeException::class, 'Device unreachable');
    expect(AttendanceDeviceSync::firstOrFail()->last_successful_sync_at->eq($lastSuccess))->toBeTrue()
        ->and(AttendanceDeviceSync::firstOrFail()->status)->toBe('failed');

    mockDeviceAttendance([deviceAttendanceRow('2026-10-01 08:00:00', 0), deviceAttendanceRow('2026-10-01 17:00:00', 1)]);
    app(SyncDeviceAttendance::class)->handle();
    expect(AttendanceSegment::count())->toBe(2)
        ->and(AttendanceDeviceSync::firstOrFail()->last_error)->toBeNull();
});

it('retains unmatched identities and replays them after employee mapping even when no longer returned by the device', function (): void {
    mockDeviceAttendance([deviceAttendanceRow('2026-11-09 08:00:00', 0, '0012'), deviceAttendanceRow('2026-11-09 17:00:00', 1, '0012')]);
    app(SyncDeviceAttendance::class)->handle();
    expect(AttendanceDevicePunch::count())->toBe(2)->and(AttendanceLog::count())->toBe(0)
        ->and(AttendanceDeviceSync::firstOrFail()->status)->toBe('needs_review');
    $this->employee->update(['attendance_device_id' => '0012']);
    mockDeviceAttendance([]);
    app(SyncDeviceAttendance::class)->handle();
    expect(AttendanceSegment::count())->toBe(1)
        ->and(AttendanceDevicePunch::whereNull('processed_at')->count())->toBe(0);
});

it('waits for a checkout instead of inventing worked time and completes on a later poll', function (): void {
    mockDeviceAttendance([deviceAttendanceRow('2026-11-09 08:00:00', 0)]);
    app(SyncDeviceAttendance::class)->handle();
    expect(AttendanceSegment::count())->toBe(0)->and(AttendanceLog::count())->toBe(1);
    mockDeviceAttendance([deviceAttendanceRow('2026-11-09 17:00:00', 1)]);
    app(SyncDeviceAttendance::class)->handle();
    expect(AttendanceSegment::count())->toBe(1)->and(AttendanceLog::count())->toBe(2);
});

it('requires a dated work assignment rather than a default shift', function (): void {
    $attributes = $this->assignment->only(['employee_id', 'work_schedule_id', 'pay_policy_id', 'effective_from', 'reason']);
    $this->assignment->delete();
    mockDeviceAttendance([deviceAttendanceRow('2026-11-09 08:00:00', 0), deviceAttendanceRow('2026-11-09 17:00:00', 1)]);
    app(SyncDeviceAttendance::class)->handle();
    expect(AttendanceSegment::count())->toBe(0);
    EmployeeWorkAssignment::create($attributes);
    app(SyncDeviceAttendance::class)->handle();
    expect(AttendanceSegment::firstOrFail()->shift_id)->toBe($this->shift->id);
});

it('handles overnight shifts using the start date', function (): void {
    $night = Shift::factory()->create(['start_time' => '22:00:00', 'end_time' => '06:00:00', 'break_start' => null, 'break_end' => null, 'break_minutes' => 0]);
    EmployeeShiftOverride::create(['employee_id' => $this->employee->id, 'date' => '2026-11-08', 'shift_id' => $night->id, 'reason' => 'Night shift']);
    mockDeviceAttendance([deviceAttendanceRow('2026-11-08 22:00:00', 0), deviceAttendanceRow('2026-11-09 06:00:00', 1)]);
    app(SyncDeviceAttendance::class)->handle();
    expect(AttendanceSegment::firstOrFail()->date->toDateString())->toBe('2026-11-08')
        ->and(AttendanceDailySummary::firstOrFail()->worked_minutes)->toBe(480);
});

it('uses actual recorded breaks without subtracting the scheduled break twice', function (): void {
    mockDeviceAttendance([
        deviceAttendanceRow('2026-11-09 08:00:00', 0), deviceAttendanceRow('2026-11-09 12:00:00', 2),
        deviceAttendanceRow('2026-11-09 13:00:00', 3), deviceAttendanceRow('2026-11-09 17:00:00', 1),
    ]);
    app(SyncDeviceAttendance::class)->handle();
    expect(AttendanceDailySummary::firstOrFail()->worked_minutes)->toBe(480);
});

it('quarantines ambiguous direction codes until sequential pairing is explicitly selected', function (): void {
    mockDeviceAttendance([deviceAttendanceRow('2026-11-09 08:00:00', 0), deviceAttendanceRow('2026-11-09 17:00:00', 0)]);
    app(SyncDeviceAttendance::class)->handle();
    expect(AttendanceSegment::count())->toBe(0)->and(AttendanceDeviceSync::firstOrFail()->last_error)->toContain('Ambiguous');
    config(['attendance_device.pairing' => 'sequential']);
    app(SyncDeviceAttendance::class)->handle();
    expect(AttendanceSegment::count())->toBe(1)->and(AttendanceLog::count())->toBe(2);
});

it('preserves imported attendance instead of double counting the day', function (): void {
    AttendanceSegment::create(['employee_id' => $this->employee->id, 'date' => '2026-11-09', 'fp_no' => '12', 'worked_minutes' => 300]);
    mockDeviceAttendance([deviceAttendanceRow('2026-11-09 08:00:00', 0), deviceAttendanceRow('2026-11-09 17:00:00', 1)]);
    app(SyncDeviceAttendance::class)->handle();
    expect(AttendanceSegment::count())->toBe(1)->and(AttendanceSegment::firstOrFail()->worked_minutes)->toBe(300)
        ->and(AttendanceDeviceSync::firstOrFail()->last_error)->toContain('takes priority');
});

it('preserves manual corrections when later punches arrive', function (): void {
    mockDeviceAttendance([deviceAttendanceRow('2026-11-09 08:00:00', 0), deviceAttendanceRow('2026-11-09 12:00:00', 2)]);
    app(SyncDeviceAttendance::class)->handle();
    AttendanceSegment::firstOrFail()->update(['worked_minutes' => 200, 'correction_reason' => 'Approved correction']);
    mockDeviceAttendance([deviceAttendanceRow('2026-11-09 13:00:00', 3), deviceAttendanceRow('2026-11-09 17:00:00', 1)]);
    app(SyncDeviceAttendance::class)->handle();
    expect(AttendanceSegment::firstOrFail()->worked_minutes)->toBe(200);
});

it('rolls back a malformed download and keeps the checkpoint unchanged', function (): void {
    mockDeviceAttendance([deviceAttendanceRow('2026-11-09 08:00:00', 0), ['user_id' => '12']]);
    expect(fn () => app(SyncDeviceAttendance::class)->handle())->toThrow(RuntimeException::class);
    expect(AttendanceDevicePunch::count())->toBe(0)->and(AttendanceDeviceSync::firstOrFail()->last_download_at)->toBeNull();
});

it('does no network or database synchronization work when disabled', function (): void {
    config(['attendance_device.enabled' => false]);
    $this->mock(ReadZktecoAttendance::class)->shouldNotReceive('handle');
    app(SyncDeviceAttendance::class)->handle();
    expect(AttendanceDeviceSync::count())->toBe(0);
});

it('can collect punches independently while shift processing is paused and replay them later', function (): void {
    config(['attendance_device.processing_enabled' => false]);
    mockDeviceAttendance([deviceAttendanceRow('2026-11-09 08:00:00', 0), deviceAttendanceRow('2026-11-09 17:00:00', 1)]);
    app(SyncDeviceAttendance::class)->handle();
    expect(AttendanceDevicePunch::count())->toBe(2)->and(AttendanceSegment::count())->toBe(0)
        ->and(AttendanceDeviceSync::firstOrFail()->last_download_at)->not->toBeNull()
        ->and(AttendanceDeviceSync::firstOrFail()->last_successful_sync_at)->not->toBeNull()
        ->and(AttendanceDeviceSync::firstOrFail()->last_error)->toBeNull()
        ->and(AttendanceDeviceSync::firstOrFail()->status)->toBe('collected');
    config(['attendance_device.processing_enabled' => true]);
    mockDeviceAttendance([]);
    app(SyncDeviceAttendance::class)->handle();
    expect(AttendanceSegment::count())->toBe(1);
});

it('retains future device timestamps unchanged and prevents them from affecting attendance', function (): void {
    mockDeviceAttendance([deviceAttendanceRow('2026-11-10 14:00:00', 0), deviceAttendanceRow('2026-11-10 15:00:00', 1)]);
    app(SyncDeviceAttendance::class)->handle();
    expect(AttendanceDevicePunch::count())->toBe(2)
        ->and(AttendanceDevicePunch::firstOrFail()->raw_data['timestamp'])->toBe('2026-11-10 14:00:00')
        ->and(AttendanceDevicePunch::firstOrFail()->punched_at->format('Y-m-d H:i:s'))->toBe('2026-11-10 14:00:00')
        ->and(AttendanceDeviceSync::firstOrFail()->last_successful_sync_at)->not->toBeNull()
        ->and(AttendanceDeviceSync::firstOrFail()->last_error)->toContain('clock')
        ->and(AttendanceSegment::count())->toBe(0);
});

it('reports unmapped AC numbers even when shift processing is paused', function (): void {
    config(['attendance_device.processing_enabled' => false]);
    mockDeviceAttendance([deviceAttendanceRow('2026-11-09 08:00:00', 0, '999')]);
    app(SyncDeviceAttendance::class)->handle();
    expect(AttendanceDeviceSync::firstOrFail()->last_error)->toContain('FP No 999')
        ->and(AttendanceDeviceSync::firstOrFail()->last_successful_sync_at)->not->toBeNull();
});

it('queues the synchronization command on the dedicated attendance queue', function (): void {
    Bus::fake();
    $this->artisan('attendance:device-sync')->assertSuccessful();
    Bus::assertDispatched(SyncJob::class, fn (SyncJob $job): bool => $job->queue === 'attendance' && $job->connection === 'database');
    config(['attendance_device.enabled' => false]);
    $this->artisan('attendance:device-sync')->assertFailed();
});

it('supports immediate collection through the same command pipeline', function (): void {
    config(['attendance_device.processing_enabled' => false]);
    mockDeviceAttendance([deviceAttendanceRow('2026-11-09 08:00:00', 0)]);
    $this->artisan('attendance:device-sync --inline')->expectsOutputToContain('1 punches read')->assertSuccessful();
    expect(AttendanceDevicePunch::count())->toBe(1)->and(AttendanceSegment::count())->toBe(0);
});

it('does not attribute old punches to a newly hired employee with a reused device identifier', function (): void {
    $this->employee->update(['hire_date' => '2026-11-10']);
    mockDeviceAttendance([deviceAttendanceRow('2026-11-09 08:00:00', 0), deviceAttendanceRow('2026-11-09 17:00:00', 1)]);
    app(SyncDeviceAttendance::class)->handle();
    expect(AttendanceSegment::count())->toBe(0)->and(AttendanceDeviceSync::firstOrFail()->last_error)->toContain('employment dates');
});

it('prevents overlapping manual and scheduled synchronization', function (): void {
    $lock = Cache::lock('attendance-device:office-mb360', 900);
    $lock->get();
    $this->mock(ReadZktecoAttendance::class)->shouldNotReceive('handle');
    expect(fn () => app(SyncDeviceAttendance::class)->handle())->toThrow(RuntimeException::class, 'already running');
    $lock->release();
});

it('previews mapping without writing attendance or synchronization state', function (): void {
    mockDeviceAttendance([deviceAttendanceRow('2026-11-09 08:00:00', 0)]);
    $this->artisan('attendance:device-preview')->expectsOutputToContain('0 unmatched')->assertSuccessful();
    expect(AttendanceDevicePunch::count())->toBe(0)->and(AttendanceDeviceSync::count())->toBe(0)->and(AttendanceLog::count())->toBe(0);
});

it('exposes status and queues manual synchronization for HR', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('hr'));
    $this->actingAs(User::factory()->create(['role' => UserRole::HR]));
    AttendanceDeviceSync::factory()->create(['device_id' => 'office-mb360', 'last_error' => 'Connection timed out', 'status' => 'failed']);
    Bus::fake();
    Livewire::test(ManageAttendanceSegments::class)->callAction('syncNow')->assertNotified();
    Bus::assertDispatched(SyncJob::class);
});

it('redirects the retired device screen to attendance for HR', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('hr'));
    $this->actingAs(User::factory()->create(['role' => UserRole::HR]));
    Livewire::test(AttendanceDevice::class)->assertRedirect(AttendanceSegmentResource::getUrl());
});

it('compares enrolled device users using AC numbers while keeping the internal employee ID separate', function (): void {
    $this->employee->update(['employee_id' => 'ERP-INTERNAL-ONLY']);
    $reader = Mockery::mock(ReadZktecoAttendance::class);
    $reader->shouldReceive('handle')->once()->andReturn([deviceAttendanceRow('2026-11-09 08:00:00', 0)]);
    $reader->shouldReceive('users')->once()->andReturn([['user_id' => '12', 'uid' => 99, 'name' => 'Machine worker']]);
    app()->instance(ReadZktecoAttendance::class, $reader);
    $this->artisan('attendance:device-preview --users')
        ->expectsOutputToContain('Machine worker')
        ->assertSuccessful();
});

it('blocks non-HR roles and disables manual synchronization until enabled', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('hr'));
    $this->actingAs(User::factory()->create(['role' => UserRole::HR]));
    config(['attendance_device.enabled' => false]);
    Bus::fake();
    Livewire::test(ManageAttendanceSegments::class)->callAction('syncNow');
    Bus::assertNotDispatched(SyncJob::class);
    $this->actingAs(User::factory()->create(['role' => UserRole::Finance]));
    expect(AttendanceDevice::canAccess())->toBeFalse();
    Livewire::test(AttendanceDevice::class)->assertRedirect();
});

it('registers a five-minute queue schedule and records terminal failures', function (): void {
    $event = collect(app(Schedule::class)->events())->first(fn ($event): bool => $event->description === 'attendance-device-sync');
    expect($event->expression)->toBe('*/5 * * * *')->and((new SyncJob)->timeout)->toBeLessThan(config('queue.connections.database.retry_after'));
    (new SyncJob)->failed(new RuntimeException('Worker timed out'));
    expect(AttendanceDeviceSync::firstOrFail()->last_error)->toBe('Worker timed out');
});

it('does not change attendance used by finalized payroll', function (): void {
    $run = PayrollRun::factory()->create(['period_start' => '2026-11-01', 'period_end' => '2026-11-30']);
    PayrollRunEmployee::withoutEvents(fn () => $run->employees()->create(['employee_id' => $this->employee->id]));
    $run->update(['status' => 'approved']);
    mockDeviceAttendance([deviceAttendanceRow('2026-11-09 08:00:00', 0), deviceAttendanceRow('2026-11-09 17:00:00', 1)]);
    app(SyncDeviceAttendance::class)->handle();
    expect(AttendanceSegment::count())->toBe(0)->and(AttendanceDeviceSync::firstOrFail()->last_error)->toContain('finalized payroll');
});

it('marks existing draft payroll attendance for review when synchronized attendance changes', function (): void {
    $run = PayrollRun::factory()->create(['period_start' => '2026-11-01', 'period_end' => '2026-11-30', 'attendance_reviewed_at' => now()]);
    mockDeviceAttendance([deviceAttendanceRow('2026-11-09 08:00:00', 0), deviceAttendanceRow('2026-11-09 17:00:00', 1)]);
    app(SyncDeviceAttendance::class)->handle();
    expect($run->fresh()->attendance_reviewed_at)->toBeNull();
});

it('prevents fallback CSV imports from double counting device attendance', function (): void {
    mockDeviceAttendance([deviceAttendanceRow('2026-11-09 08:00:00', 0), deviceAttendanceRow('2026-11-09 17:00:00', 1)]);
    app(SyncDeviceAttendance::class)->handle();
    $path = tempnam(sys_get_temp_dir(), 'attendance-');
    file_put_contents($path, "FP No,Date,Schedule,Clock In,Clock Out,In Day(Hr)\n12,11-09-26,Normal,8:00 AM,5:00 PM,8:00\n");
    try {
        $result = app(ImportAttendanceSegmentCsv::class)->handle($path);
        expect($result['import']->failed_rows)->toBe(1)->and(AttendanceSegment::count())->toBe(1);
    } finally {
        unlink($path);
    }

    $importer = new AttendanceSegmentImporter(new Import, [], []);
    $data = new ReflectionProperty($importer, 'data');
    $data->setValue($importer, ['date' => '2026-11-09', 'fp_no' => '12']);
    expect(fn () => $importer->resolveRecord())->toThrow(RowImportFailedException::class, 'Device attendance already exists');
});

it('uses the real SDK to decode a read-only attendance session and always disconnects', function (): void {
    $capacity = array_fill(0, 20, 0);
    $capacity[8] = 1;
    $payload = pack('V*', ...$capacity);
    $record = pack('v', 123).str_pad('0012', 24, "\0").chr(1)
        .DeviceClock::encode(new DateTimeImmutable('2026-11-09 08:00:00')).chr(0).str_repeat("\0", 8);
    $replies = [
        pack('vvvv', 2000, 0, 1, 0),
        pack('vvvv', 2000, 0, 1, 1).$payload,
        pack('vvvv', Opcode::DataChunk->value, 0, 1, 2).pack('V', strlen($record)).$record,
        pack('vvvv', 2000, 0, 1, 3),
    ];
    $written = [];
    $transport = Mockery::mock(Transport::class);
    $transport->shouldReceive('open')->once();
    $transport->shouldReceive('write')->andReturnUsing(function (string $packet) use (&$written): void {
        $written[] = unpack('vopcode', $packet)['opcode'];
    });
    $transport->shouldReceive('read')->andReturn(...$replies);
    $transport->shouldReceive('isOpen')->andReturn(true);
    $transport->shouldReceive('close')->once();
    $device = new ZkDevice(new DeviceConfig(host: '192.168.1.201', timezone: new DateTimeZone('Africa/Addis_Ababa')), $transport);
    $reader = new class($device) extends ReadZktecoAttendance
    {
        public function __construct(private ZkDevice $testDevice) {}

        protected function device(): ZkDevice
        {
            return $this->testDevice;
        }
    };
    $rows = $reader->handle();
    expect($rows[0]['user_id'])->toBe('0012')->and($rows[0]['uid'])->toBeNull()
        ->and($rows[0]['record_number'])->toBe(123)
        ->and($rows[0]['timestamp'])->toBe('2026-11-09 08:00:00')
        ->and($written)->toBe([Opcode::Connect->value, Opcode::ReadCapacity->value, Opcode::PrepareBuffer->value, Opcode::Disconnect->value]);
});

it('disconnects and rejects unsupported or incomplete device responses', function (bool $incomplete): void {
    $capacity = array_fill(0, 20, 0);
    $capacity[8] = 2;
    $payload = $incomplete ? pack('V*', ...$capacity) : 'short';
    $record = pack('v', 1).str_pad('12', 24, "\0").chr(1)
        .DeviceClock::encode(new DateTimeImmutable('2026-11-09 08:00:00')).chr(0).str_repeat("\0", 8);
    $replies = [pack('vvvv', 2000, 0, 1, 0), pack('vvvv', 2000, 0, 1, 1).$payload];

    if ($incomplete) {
        $replies[] = pack('vvvv', Opcode::DataChunk->value, 0, 1, 2).pack('V', strlen($record)).$record;
    }

    $replies[] = pack('vvvv', 2000, 0, 1, 3);
    $transport = Mockery::mock(Transport::class);
    $transport->shouldReceive('open')->once();
    $transport->shouldReceive('write');
    $transport->shouldReceive('read')->andReturn(...$replies);
    $transport->shouldReceive('isOpen')->andReturn(true);
    $transport->shouldReceive('close')->once();
    $device = new ZkDevice(new DeviceConfig(host: '192.168.1.201'), $transport);
    $reader = new class($device) extends ReadZktecoAttendance
    {
        public function __construct(private ZkDevice $testDevice) {}

        protected function device(): ZkDevice
        {
            return $this->testDevice;
        }
    };

    expect(fn () => $reader->handle())->toThrow(RuntimeException::class, $incomplete ? 'Incomplete attendance download' : 'Unsupported device capacity');
})->with(['unsupported firmware response' => false, 'partial download' => true]);

<?php

use App\Models\AttendanceDevicePunch;
use App\Models\AttendanceDeviceSync;
use App\Models\AttendanceLog;
use App\Models\AttendanceSegment;
use App\Models\Employee;
use App\Models\EmployeeWorkAssignment;
use App\Models\Holiday;
use App\Models\PayrollOvertimeEntry;
use App\Models\PayrollRun;
use App\Models\PayrollRunEmployee;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\Hr\NotifyUnmatchedAttendanceNumbers;
use App\Services\Hr\ProcessDeviceAttendance;
use App\Services\Hr\ReadZktecoAttendance;
use App\Services\Hr\SyncDeviceAttendance;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['attendance_device.enabled' => true, 'attendance_device.processing_enabled' => true, 'attendance_device.pairing' => 'device', 'attendance_device.sync_from' => null]);
    $this->travelTo(now()->setDate(2026, 11, 10)->setTime(12, 0));
    $this->employee = Employee::factory()->create(['attendance_device_id' => '12']);
    $this->shift = Shift::factory()->create(['start_time' => '08:00:00', 'end_time' => '17:00:00', 'break_start' => null, 'break_end' => null, 'break_minutes' => 0, 'capture_after_minutes' => 240]);
    $schedule = WorkSchedule::create(['name' => 'Exception test']);
    foreach (range(0, 6) as $day) {
        $schedule->days()->create(['day_of_week' => $day, 'shift_id' => $this->shift->id]);
    }
    $this->assignment = EmployeeWorkAssignment::factory()->create(['employee_id' => $this->employee->id, 'work_schedule_id' => $schedule->id]);
});

function exceptionPunch(string $timestamp, int $direction = 0, string $fp = '12'): AttendanceDevicePunch
{
    return AttendanceDevicePunch::factory()->create(['device_id' => 'office-mb360', 'device_user_id' => $fp, 'punched_at' => $timestamp, 'direction' => $direction]);
}

it('notifies admin and HR once for each unresolved FP number while keeping leading zeros', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $hr = User::factory()->create(['role' => UserRole::HR]);
    $finance = User::factory()->create(['role' => UserRole::Finance]);
    exceptionPunch('2026-11-09 08:00:00', fp: '0012');
    app(ProcessDeviceAttendance::class)->handle('office-mb360');
    app(ProcessDeviceAttendance::class)->handle('office-mb360');
    foreach ([$admin, $hr] as $recipient) {
        expect($recipient->notifications()->count())->toBe(1)
            ->and($recipient->notifications()->first()->data['body'])->toContain('0012');
    }
    expect($finance->notifications()->count())->toBe(0);
    exceptionPunch('2026-11-09 09:00:00', fp: '999');
    app(ProcessDeviceAttendance::class)->handle('office-mb360');
    expect($admin->notifications()->count())->toBe(2)
        ->and(NotifyUnmatchedAttendanceNumbers::unmatchedIds('office-mb360')->all())->toBe(['0012', '999']);
    Employee::factory()->create(['attendance_device_id' => '0012']);
    app(ProcessDeviceAttendance::class)->handle('office-mb360');
    expect(AttendanceDeviceSync::firstOrFail()->notified_unmatched_ids)->toBe(['999']);
});

it('waits until the capture window closes then fills the scheduled checkout without altering raw punches', function (): void {
    $punch = exceptionPunch('2026-11-09 08:00:00');
    $raw = $punch->only(['fingerprint', 'punched_at', 'direction', 'raw_data']);
    $this->travelTo(now()->setDate(2026, 11, 9)->setTime(20, 59));
    app(ProcessDeviceAttendance::class)->handle('office-mb360');
    expect(AttendanceSegment::count())->toBe(0);
    $this->travel(1)->minutes();
    app(ProcessDeviceAttendance::class)->handle('office-mb360');
    $segment = AttendanceSegment::sole();
    expect($segment->clock_out)->toBe('17:00:00')->and($segment->exception)->toBe('Estimated check-out')
        ->and($segment->raw_data['estimated_checkout']['shift_id'])->toBe($this->shift->id)
        ->and(AttendanceDevicePunch::count())->toBe(1)->and(AttendanceLog::count())->toBe(1)
        ->and($punch->fresh()->only(array_keys($raw)))->toEqual($raw);
});

it('replaces an estimate with a later real punch and retains one attendance record', function (): void {
    exceptionPunch('2026-11-09 08:00:00');
    app(ProcessDeviceAttendance::class)->handle('office-mb360');
    $id = AttendanceSegment::sole()->id;
    exceptionPunch('2026-11-09 18:00:00', 1);
    app(ProcessDeviceAttendance::class)->handle('office-mb360');
    $segment = AttendanceSegment::sole();
    expect($segment->id)->toBe($id)->and($segment->clock_out)->toBe('18:00:00')
        ->and($segment->raw_data)->not->toHaveKey('estimated_checkout')->and($segment->exception)->toBeNull();
});

it('handles overnight estimates and matches a real checkout outside the capture window back to the work date', function (): void {
    $this->shift->update(['start_time' => '22:00:00', 'end_time' => '06:00:00']);
    exceptionPunch('2026-11-08 22:00:00');
    app(ProcessDeviceAttendance::class)->handle('office-mb360');
    expect(AttendanceSegment::sole()->date->toDateString())->toBe('2026-11-08')
        ->and(AttendanceSegment::sole()->clock_out)->toBe('06:00:00');
    exceptionPunch('2026-11-09 11:00:00', 1);
    app(ProcessDeviceAttendance::class)->handle('office-mb360');
    expect(AttendanceSegment::sole()->clock_out)->toBe('11:00:00')
        ->and(AttendanceSegment::sole()->raw_data)->not->toHaveKey('estimated_checkout');
});

it('uses the matching split shift end rather than the end of the whole workday', function (): void {
    $this->shift->update(['end_time' => '12:00:00']);
    $afternoon = Shift::factory()->create(['start_time' => '13:00:00', 'end_time' => '17:00:00', 'break_start' => null, 'break_end' => null]);
    $replacement = $this->assignment->replicate();
    $this->assignment->delete();
    $this->assignment->workSchedule->days()->where('day_of_week', 1)->firstOrFail()->update(['shift_ids' => [$this->shift->id, $afternoon->id]]);
    $replacement->save();
    exceptionPunch('2026-11-09 08:00:00');
    app(ProcessDeviceAttendance::class)->handle('office-mb360');
    expect(AttendanceSegment::sole()->clock_out)->toBe('12:00:00');
});

it('does not repair ambiguous or unscheduled punches', function (array $directions, bool $rest): void {
    if ($rest) {
        $replacement = $this->assignment->replicate();
        $this->assignment->delete();
        $this->assignment->workSchedule->days()->where('day_of_week', 1)->firstOrFail()->update(['shift_ids' => []]);
        $replacement->save();
    }
    foreach ($directions as $index => $direction) {
        exceptionPunch('2026-11-09 '.sprintf('%02d:00:00', 8 + $index), $direction);
    }
    app(ProcessDeviceAttendance::class)->handle('office-mb360');
    expect(AttendanceSegment::count())->toBe(0)->and(AttendanceDevicePunch::whereNull('processed_at')->count())->toBe(count($directions));
})->with([
    [[0, 0], false], [[1], false], [[9], false], [[0], true],
]);

it('does not award overtime for estimated time even on holidays or early check-ins', function (): void {
    Holiday::create(['name' => 'Test holiday', 'date' => '2026-11-09', 'counts_as_holiday_overtime' => true, 'is_paid' => true]);
    exceptionPunch('2026-11-09 07:00:00');
    app(ProcessDeviceAttendance::class)->handle('office-mb360');
    expect(PayrollOvertimeEntry::where('minutes', '>', 0)->count())->toBe(0);
    exceptionPunch('2026-11-09 17:00:00', 1);
    app(ProcessDeviceAttendance::class)->handle('office-mb360');
    expect(PayrollOvertimeEntry::where('minutes', '>', 0)->count())->toBeGreaterThan(0);
});

it('does not change an estimate belonging to finalized payroll', function (): void {
    exceptionPunch('2026-11-09 08:00:00');
    app(ProcessDeviceAttendance::class)->handle('office-mb360');
    $run = PayrollRun::factory()->create(['status' => 'approved', 'period_start' => '2026-11-01', 'period_end' => '2026-11-30']);
    PayrollRunEmployee::withoutEvents(fn () => $run->employees()->create(['employee_id' => $this->employee->id]));
    $real = exceptionPunch('2026-11-09 18:00:00', 1);
    app(ProcessDeviceAttendance::class)->handle('office-mb360');
    expect(AttendanceSegment::sole()->clock_out)->toBe('17:00:00')
        ->and($real->fresh()->processed_at)->toBeNull()->and($real->fresh()->processing_error)->toContain('finalized payroll');
});

it('can close a stored attendance day during a device outage without advancing the download checkpoint', function (): void {
    exceptionPunch('2026-11-09 08:00:00');
    $state = AttendanceDeviceSync::factory()->create(['device_id' => 'office-mb360', 'last_successful_sync_at' => '2026-11-09 12:00:00']);
    $reader = Mockery::mock(ReadZktecoAttendance::class);
    $reader->shouldReceive('handle')->andThrow(new RuntimeException('Device unreachable'));
    app()->instance(ReadZktecoAttendance::class, $reader);
    expect(fn () => app(SyncDeviceAttendance::class)->handle())->toThrow(RuntimeException::class);
    expect(AttendanceSegment::sole()->clock_out)->toBe('17:00:00')
        ->and($state->fresh()->status)->toBe('failed')
        ->and($state->fresh()->last_successful_sync_at->toDateTimeString())->toBe('2026-11-09 12:00:00');
});

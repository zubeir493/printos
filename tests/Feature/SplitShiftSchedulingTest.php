<?php

use App\Filament\Resources\Employees\Pages\EditEmployee;
use App\Filament\Resources\Employees\RelationManagers\ShiftOverridesRelationManager;
use App\Filament\Resources\Employees\RelationManagers\WorkAssignmentsRelationManager;
use App\Models\AttendanceDailySummary;
use App\Models\AttendanceDevicePunch;
use App\Models\AttendanceSegment;
use App\Models\Employee;
use App\Models\EmployeeShiftOverride;
use App\Models\EmployeeWorkAssignment;
use App\Models\LeaveType;
use App\Models\PayPolicy;
use App\Models\PayrollOvertimeEntry;
use App\Models\PayrollRun;
use App\Models\PayrollRunEmployee;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\Hr\BuildScheduledAttendance;
use App\Services\Hr\CalculatePayrollRun;
use App\Services\Hr\GeneratePayrollOvertimeEntries;
use App\Services\Hr\PayrollInputs;
use App\Services\Hr\ReadZktecoAttendance;
use App\Services\Hr\ResolveEmployeeWork;
use App\Services\Hr\SyncDeviceAttendance;
use App\UserRole;
use Carbon\CarbonImmutable;
use Database\Seeders\PayrollTaxRuleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-10 12:00:00'));
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $this->employee = Employee::factory()->create(['attendance_device_id' => '12', 'basic_salary' => 12000]);
    $this->morning = Shift::factory()->create(['start_time' => '08:00:00', 'end_time' => '12:00:00', 'break_start' => null, 'break_end' => null]);
    $this->evening = Shift::factory()->create(['start_time' => '17:00:00', 'end_time' => '21:00:00', 'break_start' => null, 'break_end' => null]);
    $this->schedule = WorkSchedule::create(['name' => 'Split working week']);
    foreach (range(0, 6) as $weekday) {
        $this->schedule->days()->create(['day_of_week' => $weekday, 'shift_ids' => $weekday === 1 ? [$this->morning->id, $this->evening->id] : []]);
    }
});

function assignSplitWork(object $test): EmployeeWorkAssignment
{
    return EmployeeWorkAssignment::factory()->create(['employee_id' => $test->employee->id, 'work_schedule_id' => $test->schedule->id]);
}

function splitPunchPair(Employee $employee, string $from, string $to): void
{
    $employee->attendanceSegments()->create(['date' => '2026-11-02', 'fp_no' => $employee->attendance_device_id, 'clock_in' => $from, 'clock_out' => $to, 'status' => 'Present']);
}

it('resolves multiple independent shifts in one employee workday', function (): void {
    assignSplitWork($this);
    $work = app(ResolveEmployeeWork::class)->forDate($this->employee, '2026-11-02');
    expect($work['shifts']->modelKeys())->toBe([$this->morning->id, $this->evening->id]);
    expect($this->schedule->days()->where('day_of_week', 1)->firstOrFail()->shift_ids)->toBe([$this->morning->id, $this->evening->id]);
});

it('counts split work as eight scheduled hours without paying the gap or generating overtime', function (): void {
    assignSplitWork($this);
    splitPunchPair($this->employee, '08:00:00', '12:00:00');
    splitPunchPair($this->employee, '17:00:00', '21:00:00');
    app(GeneratePayrollOvertimeEntries::class)->generate($this->employee, '2026-11-02', '2026-11-02');
    $summary = AttendanceDailySummary::firstOrFail();
    expect($summary->expected_minutes)->toBe(480)->and($summary->regular_minutes)->toBe(480)
        ->and($summary->calculation_snapshot['issues'])->toBe([])->and(PayrollOvertimeEntry::count())->toBe(0);
});

it('pays overtime around both scheduled periods without counting normal shift time twice', function (): void {
    assignSplitWork($this);
    splitPunchPair($this->employee, '08:00:00', '13:00:00');
    splitPunchPair($this->employee, '17:00:00', '21:30:00');
    $generator = app(GeneratePayrollOvertimeEntries::class);
    $generator->generate($this->employee, '2026-11-02', '2026-11-02');
    $generator->generate($this->employee, '2026-11-02', '2026-11-02');
    expect(PayrollOvertimeEntry::count())->toBe(1)->and(PayrollOvertimeEntry::firstOrFail()->minutes)->toBe(90)
        ->and((float) PayrollOvertimeEntry::firstOrFail()->amount)->toBe(112.5);
});

it('rejects duplicate and overlapping shifts in a day', function (): void {
    $day = $this->schedule->days()->where('day_of_week', 1)->firstOrFail();
    expect(fn () => $day->update(['shift_ids' => [$this->morning->id, $this->morning->id]]))->toThrow(ValidationException::class);
    $overlap = Shift::factory()->create(['start_time' => '10:00:00', 'end_time' => '14:00:00', 'break_start' => null, 'break_end' => null]);
    expect(fn () => $day->fresh()->update(['shift_ids' => [$this->morning->id, $overlap->id]]))->toThrow(ValidationException::class);
});

it('rejects overnight periods overlapping the following workday', function (): void {
    $night = Shift::factory()->create(['start_time' => '22:00:00', 'end_time' => '06:00:00', 'break_start' => null, 'break_end' => null]);
    $early = Shift::factory()->create(['start_time' => '04:00:00', 'end_time' => '08:00:00', 'break_start' => null, 'break_end' => null]);
    $this->schedule->days()->where('day_of_week', 1)->firstOrFail()->update(['shift_ids' => [$night->id]]);
    $this->schedule->days()->where('day_of_week', 2)->firstOrFail()->update(['shift_ids' => [$early->id]]);
    expect(fn () => assignSplitWork($this))->toThrow(ValidationException::class);
});

it('maps overnight checkout to its workday even when another shift occurs earlier that day', function (): void {
    $night = Shift::factory()->create(['start_time' => '22:00:00', 'end_time' => '06:00:00', 'break_start' => null, 'break_end' => null]);
    $this->schedule->days()->where('day_of_week', 1)->firstOrFail()->update(['shift_ids' => [$this->morning->id, $night->id]]);
    assignSplitWork($this);
    $work = app(ResolveEmployeeWork::class)->forPunch($this->employee, CarbonImmutable::parse('2026-11-03 06:00:00'), 1);
    expect($work['date'])->toBe('2026-11-02')->and($work['shifts'])->toHaveCount(2);
});

it('uses punch direction when an overnight shift ends as the next workday starts', function (): void {
    $night = Shift::factory()->create(['start_time' => '22:00:00', 'end_time' => '06:00:00', 'break_start' => null, 'break_end' => null]);
    $early = Shift::factory()->create(['start_time' => '06:00:00', 'end_time' => '10:00:00', 'break_start' => null, 'break_end' => null]);
    $this->schedule->days()->where('day_of_week', 1)->firstOrFail()->update(['shift_ids' => [$night->id]]);
    $this->schedule->days()->where('day_of_week', 2)->firstOrFail()->update(['shift_ids' => [$early->id]]);
    assignSplitWork($this);
    $resolver = app(ResolveEmployeeWork::class);
    $time = CarbonImmutable::parse('2026-11-03 06:00:00');
    expect($resolver->forPunch($this->employee, $time, 1)['date'])->toBe('2026-11-02')
        ->and($resolver->forPunch($this->employee, $time, 0)['date'])->toBe('2026-11-03');
});

it('supports multi-shift date overrides and editable secondary shifts', function (): void {
    assignSplitWork($this);
    $override = EmployeeShiftOverride::create(['employee_id' => $this->employee->id, 'date' => '2026-11-04', 'shift_ids' => [$this->morning->id, $this->evening->id], 'reason' => 'Extra split day']);
    expect(app(ResolveEmployeeWork::class)->forDate($this->employee, '2026-11-04')['shifts'])->toHaveCount(2);
    $this->evening->update(['end_time' => '22:00:00']);
    expect($override->fresh()->resolvedShifts()->last()->end_time)->toBe('22:00:00');
    $override->update(['shift_ids' => []]);
    expect($override->fresh()->shift_ids)->toBe([]);
});

it('handles approved leave covering one period of a split day', function (): void {
    assignSplitWork($this);
    $type = LeaveType::create(['name' => 'Paid time', 'is_paid' => true]);
    $this->employee->leaveRequests()->create(['leave_type_id' => $type->id, 'start_date' => '2026-11-02', 'end_date' => '2026-11-02', 'minutes' => 240, 'status' => 'approved']);
    splitPunchPair($this->employee, '17:00:00', '21:00:00');
    $summary = app(BuildScheduledAttendance::class)->handle($this->employee, '2026-11-02', '2026-11-02')->firstOrFail();
    expect($summary->paid_leave_minutes)->toBe(240)->and($summary->regular_minutes)->toBe(240)->and($summary->calculation_snapshot['issues'])->toBe([]);
});

it('does not multiply salary when an employee has multiple shifts', function (): void {
    $this->seed(PayrollTaxRuleSeeder::class);
    assignSplitWork($this);
    splitPunchPair($this->employee, '08:00:00', '12:00:00');
    splitPunchPair($this->employee, '17:00:00', '21:00:00');
    $run = PayrollRun::factory()->create(['period_type' => 'custom', 'period_start' => '2026-11-02', 'period_end' => '2026-11-02']);
    app(CalculatePayrollRun::class)->handle($run, [$this->employee->id]);
    expect((float) $run->employees()->firstOrFail()->basic_salary)->toBe(400.0)
        ->and((float) $run->employees()->firstOrFail()->overtime_amount)->toBe(0.0);
});

it('synchronizes all four punches into one split workday idempotently', function (): void {
    assignSplitWork($this);
    config(['attendance_device.enabled' => true, 'attendance_device.processing_enabled' => true, 'attendance_device.pairing' => 'device', 'attendance_device.sync_from' => null]);
    $rows = collect([['08:00:00', 0], ['12:00:00', 1], ['17:00:00', 0], ['21:00:00', 1]])->map(fn (array $item): array => ['user_id' => '12', 'uid' => 99, 'timestamp' => '2026-11-02 '.$item[0], 'raw_direction' => $item[1], 'raw_verify' => 1])->all();
    $reader = Mockery::mock(ReadZktecoAttendance::class);
    $reader->shouldReceive('handle')->andReturn($rows);
    app()->instance(ReadZktecoAttendance::class, $reader);
    app(SyncDeviceAttendance::class)->handle();
    app(SyncDeviceAttendance::class)->handle();
    expect(AttendanceDevicePunch::count())->toBe(4)->and(AttendanceSegment::count())->toBe(1)
        ->and(AttendanceDailySummary::firstOrFail()->regular_minutes)->toBe(480)->and(PayrollOvertimeEntry::count())->toBe(0);
});

it('keeps late arrival and early departure totals separate across device shift pairs', function (): void {
    assignSplitWork($this);
    config(['attendance_device.enabled' => true, 'attendance_device.processing_enabled' => true, 'attendance_device.pairing' => 'device', 'attendance_device.sync_from' => null]);
    $rows = collect([['08:15:00', 0], ['11:45:00', 1], ['17:10:00', 0], ['20:40:00', 1]])->map(fn (array $item): array => ['user_id' => '12', 'uid' => 99, 'timestamp' => '2026-11-02 '.$item[0], 'raw_direction' => $item[1], 'raw_verify' => 1])->all();
    $reader = Mockery::mock(ReadZktecoAttendance::class);
    $reader->shouldReceive('handle')->once()->andReturn($rows);
    app()->instance(ReadZktecoAttendance::class, $reader);
    app(SyncDeviceAttendance::class)->handle();
    $segment = AttendanceSegment::firstOrFail();
    expect($segment->late_minutes)->toBe(25)->and($segment->early_minutes)->toBe(35)
        ->and(AttendanceDailySummary::firstOrFail()->late_minutes)->toBe(60);
});

it('calculates an overnight second period and its overtime on the following date', function (): void {
    $night = Shift::factory()->create(['start_time' => '22:00:00', 'end_time' => '06:00:00', 'break_start' => null, 'break_end' => null]);
    $this->schedule->days()->where('day_of_week', 1)->firstOrFail()->update(['shift_ids' => [$this->morning->id, $night->id]]);
    assignSplitWork($this);
    splitPunchPair($this->employee, '08:00:00', '12:00:00');
    splitPunchPair($this->employee, '22:00:00', '06:30:00');
    app(GeneratePayrollOvertimeEntries::class)->generate($this->employee, '2026-11-02', '2026-11-02');
    expect(AttendanceDailySummary::firstOrFail()->regular_minutes)->toBe(720);
    $entry = PayrollOvertimeEntry::firstOrFail();
    expect($entry->date->toDateString())->toBe('2026-11-03')->and($entry->category)->toBe('day')
        ->and($entry->minutes)->toBe(30)->and((float) $entry->amount)->toBe(37.5);
});

it('saves multiple selected shifts through the weekly editor', function (): void {
    PayPolicy::factory()->create();
    Filament::setCurrentPanel(Filament::getPanel('hr'));
    $component = Livewire::test(WorkAssignmentsRelationManager::class, ['ownerRecord' => $this->employee, 'pageClass' => EditEmployee::class])->mountAction(TestAction::make('create')->table())->set('mountedActions.0.data.effective_from', '2026-11-01')->set('mountedActions.0.data.reason', 'Weekly setup');
    $days = $component->get('mountedActions.0.data.days');
    foreach ($days as &$day) {
        $day['shift_ids'] = (int) $day['day_of_week'] === 1 ? [$this->morning->id, $this->evening->id] : [];
    }
    unset($day);
    $component->set('mountedActions.0.data.days', $days)->callMountedAction()->assertHasNoActionErrors();
    expect($this->employee->workAssignments()->firstOrFail()->workSchedule->days()->where('day_of_week', 1)->firstOrFail()->shift_ids)->toBe([$this->morning->id, $this->evening->id]);
});

it('holds payroll and overtime when an entire scheduled period is missing', function (): void {
    assignSplitWork($this);
    splitPunchPair($this->employee, '08:00:00', '13:00:00');
    app(GeneratePayrollOvertimeEntries::class)->generate($this->employee, '2026-11-02', '2026-11-02');
    $summary = AttendanceDailySummary::firstOrFail();
    expect($summary->status)->toBe('needs_review')->and($summary->regular_minutes)->toBe(240)
        ->and($summary->calculation_snapshot['issues'])->not->toBeEmpty()->and(PayrollOvertimeEntry::count())->toBe(0);
});

it('subtracts each split period unpaid break once', function (): void {
    $this->morning->update(['break_start' => '10:00:00', 'break_end' => '10:15:00', 'break_paid' => false]);
    $this->evening->update(['break_start' => '19:00:00', 'break_end' => '19:15:00', 'break_paid' => false]);
    assignSplitWork($this);
    splitPunchPair($this->employee, '08:00:00', '12:00:00');
    splitPunchPair($this->employee, '17:00:00', '21:00:00');
    $summary = app(BuildScheduledAttendance::class)->handle($this->employee, '2026-11-02', '2026-11-02')->firstOrFail();
    expect($summary->expected_minutes)->toBe(450)->and($summary->regular_minutes)->toBe(450)
        ->and($summary->overtime_minutes)->toBe(0)->and($summary->calculation_snapshot['issues'])->toBe([]);
});

it('rejects an override overlapping overnight work and rolls back its shifts', function (): void {
    $night = Shift::factory()->create(['start_time' => '22:00:00', 'end_time' => '06:00:00', 'break_start' => null, 'break_end' => null]);
    $this->schedule->days()->where('day_of_week', 1)->firstOrFail()->update(['shift_ids' => [$night->id]]);
    assignSplitWork($this);
    $early = Shift::factory()->create(['start_time' => '05:00:00', 'end_time' => '09:00:00', 'break_start' => null, 'break_end' => null]);
    expect(fn () => EmployeeShiftOverride::create(['employee_id' => $this->employee->id, 'date' => '2026-11-03', 'shift_ids' => [$early->id, $this->evening->id], 'reason' => 'Conflict']))->toThrow(ValidationException::class);
    expect(EmployeeShiftOverride::count())->toBe(0);
    $this->assertDatabaseCount('employee_shift_override_shifts', 0);
});

it('rejects a replacement schedule overlapping the previous assignment overnight', function (): void {
    $night = Shift::factory()->create(['start_time' => '22:00:00', 'end_time' => '06:00:00', 'break_start' => null, 'break_end' => null]);
    $this->schedule->days()->where('day_of_week', 1)->firstOrFail()->update(['shift_ids' => [$night->id]]);
    EmployeeWorkAssignment::factory()->create(['employee_id' => $this->employee->id, 'work_schedule_id' => $this->schedule->id, 'effective_to' => '2026-11-02']);
    $replacement = WorkSchedule::factory()->create();
    $early = Shift::factory()->create(['start_time' => '05:00:00', 'end_time' => '09:00:00', 'break_start' => null, 'break_end' => null]);
    $replacement->days()->where('day_of_week', 2)->firstOrFail()->update(['shift_ids' => [$early->id]]);
    expect(fn () => EmployeeWorkAssignment::factory()->create(['employee_id' => $this->employee->id, 'work_schedule_id' => $replacement->id, 'effective_from' => '2026-11-03']))->toThrow(ValidationException::class);
    expect($this->employee->workAssignments()->count())->toBe(1);
});

it('rolls back an overlapping weekly pattern in the editor', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('hr'));
    $night = Shift::factory()->create(['start_time' => '22:00:00', 'end_time' => '06:00:00', 'break_start' => null, 'break_end' => null]);
    $early = Shift::factory()->create(['start_time' => '05:00:00', 'end_time' => '09:00:00', 'break_start' => null, 'break_end' => null]);
    $component = Livewire::test(WorkAssignmentsRelationManager::class, ['ownerRecord' => $this->employee, 'pageClass' => EditEmployee::class])->mountAction(TestAction::make('create')->table())->set('mountedActions.0.data.effective_from', '2026-11-01')->set('mountedActions.0.data.reason', 'Weekly setup');
    $days = $component->get('mountedActions.0.data.days');
    foreach ($days as &$day) {
        $day['shift_ids'] = match ((int) $day['day_of_week']) {
            1 => [$night->id], 2 => [$early->id], default => []
        };
    }
    unset($day);
    $component->set('mountedActions.0.data.days', $days)->callMountedAction()->assertHasActionErrors(['days']);
    expect($this->employee->workAssignments()->count())->toBe(0);
});

it('saves and edits multiple shifts through the employee date override form', function (): void {
    assignSplitWork($this);
    Filament::setCurrentPanel(Filament::getPanel('hr'));
    Livewire::test(ShiftOverridesRelationManager::class, ['ownerRecord' => $this->employee, 'pageClass' => EditEmployee::class])
        ->callAction(TestAction::make('create')->table(), ['date' => '2026-11-04', 'shift_ids' => [$this->morning->id, $this->evening->id], 'reason' => 'Extra day'])
        ->assertHasNoActionErrors();
    $override = EmployeeShiftOverride::firstOrFail();
    Livewire::test(ShiftOverridesRelationManager::class, ['ownerRecord' => $this->employee, 'pageClass' => EditEmployee::class])
        ->callAction(TestAction::make('edit')->table($override), ['shift_ids' => [], 'reason' => 'Rest instead'])
        ->assertHasNoActionErrors();
    expect($override->fresh()->shift_ids)->toBe([]);
});

it('blocks date overrides affecting approved payroll', function (): void {
    assignSplitWork($this);
    $run = PayrollRun::factory()->create(['period_start' => '2026-11-01', 'period_end' => '2026-11-30']);
    PayrollRunEmployee::withoutEvents(fn () => $run->employees()->create(['employee_id' => $this->employee->id]));
    $run->update(['status' => 'approved']);
    expect(fn () => EmployeeShiftOverride::create(['employee_id' => $this->employee->id, 'date' => '2026-11-04', 'shift_ids' => [], 'reason' => 'Too late']))->toThrow(ValidationException::class);
});

it('changes payroll fingerprints when only a secondary override shift changes', function (): void {
    assignSplitWork($this);
    $override = EmployeeShiftOverride::create(['employee_id' => $this->employee->id, 'date' => '2026-11-02', 'shift_ids' => [$this->morning->id, $this->evening->id], 'reason' => 'Split day']);
    $before = PayrollInputs::signature($this->employee, '2026-11-02', '2026-11-02');
    $replacement = Shift::factory()->create(['start_time' => '17:00:00', 'end_time' => '20:00:00', 'break_start' => null, 'break_end' => null]);
    $override->update(['shift_ids' => [$this->morning->id, $replacement->id]]);
    expect(PayrollInputs::signature($this->employee, '2026-11-02', '2026-11-02'))->not->toBe($before);
});

it('shows every assigned period in the employee shift editor', function (): void {
    assignSplitWork($this);
    Filament::setCurrentPanel(Filament::getPanel('hr'));
    $assignment = $this->employee->workAssignments()->firstOrFail();
    $component = Livewire::test(WorkAssignmentsRelationManager::class, ['ownerRecord' => $this->employee, 'pageClass' => EditEmployee::class])
        ->mountAction(TestAction::make('edit')->table($assignment));
    $monday = collect($component->get('mountedActions.0.data.days'))->firstWhere('day_of_week', 1);
    expect(array_map('intval', $monday['shift_ids']))->toBe([$this->morning->id, $this->evening->id]);
});

it('shows override overlap errors on the shift field', function (): void {
    assignSplitWork($this);
    Filament::setCurrentPanel(Filament::getPanel('hr'));
    $overlap = Shift::factory()->create(['start_time' => '10:00:00', 'end_time' => '14:00:00', 'break_start' => null, 'break_end' => null]);
    Livewire::test(ShiftOverridesRelationManager::class, ['ownerRecord' => $this->employee, 'pageClass' => EditEmployee::class])
        ->callAction(TestAction::make('create')->table(), ['date' => '2026-11-04', 'shift_ids' => [$this->morning->id, $overlap->id], 'reason' => 'Conflict'])
        ->assertHasActionErrors(['shift_ids']);
    expect(EmployeeShiftOverride::count())->toBe(0);
});

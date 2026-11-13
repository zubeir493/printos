<?php

use App\Exceptions\HrWorkflowException;
use App\Models\AttendanceSegment;
use App\Models\Employee;
use App\Models\EmployeeShiftOverride;
use App\Models\EmployeeWorkAssignment;
use App\Models\Holiday;
use App\Models\LeaveType;
use App\Models\PayPolicy;
use App\Models\PayrollOvertimeEntry;
use App\Models\PayrollRun;
use App\Models\Shift;
use App\Models\User;
use App\Services\Hr\BuildScheduledAttendance;
use App\Services\Hr\CalculatePayrollRun;
use App\Services\Hr\GeneratePayrollOvertimeEntries;
use App\Services\Hr\PayrollInputs;
use App\Services\Hr\PostPayrollRun;
use App\Services\Hr\ReviewOvertime;
use App\Services\Hr\ReviseEmployeeSalary;
use App\UserRole;
use Database\Seeders\PayrollTaxRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(PayrollTaxRuleSeeder::class);
    $this->travelTo(now()->setDate(2026, 12, 5)->setTime(12, 0));
    $this->employee = Employee::factory()->create(['attendance_device_id' => '12', 'basic_salary' => 12000]);
    $this->assignment = EmployeeWorkAssignment::factory()->create(['employee_id' => $this->employee->id]);
    $this->actingAs(User::factory()->create(['role' => UserRole::HR]));
});

function policyAttendance(Employee $employee, string $date, ?string $in, ?string $out, string $status = 'Present'): AttendanceSegment
{
    return AttendanceSegment::create(['employee_id' => $employee->id, 'date' => $date, 'fp_no' => $employee->attendance_device_id, 'clock_in' => $in, 'clock_out' => $out, 'status' => $status]);
}

it('starts monthly payroll from full salary entitlement and holds missing attendance for review', function (): void {
    $run = PayrollRun::factory()->create(['period_type' => 'monthly', 'period_start' => '2026-11-01', 'period_end' => '2026-11-30']);
    app(CalculatePayrollRun::class)->handle($run);
    $row = $run->employees()->firstOrFail();
    expect((float) $row->basic_salary)->toBe(12000.0)
        ->and($row->calculation_snapshot['attendance_issues'])->not->toBeEmpty();
});

it('reduces approved unpaid absence once using the policy hourly basis', function (): void {
    policyAttendance($this->employee, '2026-11-02', null, null, 'Absent');
    $run = PayrollRun::factory()->create(['period_type' => 'monthly', 'period_start' => '2026-11-01', 'period_end' => '2026-11-30']);
    app(CalculatePayrollRun::class)->handle($run);
    $row = $run->employees()->firstOrFail();
    expect((float) $row->basic_salary)->toBe(11600.0)
        ->and((float) $row->penalty_amount)->toBe(0.0)
        ->and((float) $row->calculation_snapshot['unpaid_time_reduction'])->toBe(400.0);
});

it('prorates a mid-month salary revision by its actual effective date', function (): void {
    app(ReviseEmployeeSalary::class)->handle($this->employee, 15000, '2026-11-16', 'Salary revision');
    $run = PayrollRun::factory()->create(['period_type' => 'monthly', 'period_start' => '2026-11-01', 'period_end' => '2026-11-30']);
    app(CalculatePayrollRun::class)->handle($run);
    expect((float) $run->employees()->firstOrFail()->basic_salary)->toBe(13500.0);
});

it('generates exclusive overtime minutes outside scheduled work and preserves approval on repeat collection', function (): void {
    policyAttendance($this->employee, '2026-11-02', '08:00:00', '19:00:00');
    $generator = app(GeneratePayrollOvertimeEntries::class);
    $generator->generate($this->employee, '2026-11-02', '2026-11-02');
    $entry = PayrollOvertimeEntry::firstOrFail();
    expect($entry->minutes)->toBe(120)->and((float) $entry->amount)->toBe(150.0);
    app(ReviewOvertime::class)->handle($entry, true, 'Approved production overtime');
    $generator->generate($this->employee, '2026-11-02', '2026-11-02');
    expect(PayrollOvertimeEntry::count())->toBe(1)->and($entry->fresh()->status)->toBe('approved');
});

it('treats a scheduled night shift as ordinary work', function (): void {
    $night = Shift::factory()->create(['start_time' => '22:00:00', 'end_time' => '06:00:00', 'break_start' => null, 'break_end' => null, 'break_minutes' => 0]);
    EmployeeShiftOverride::create(['employee_id' => $this->employee->id, 'date' => '2026-11-02', 'shift_id' => $night->id, 'reason' => 'Temporary night work']);
    policyAttendance($this->employee, '2026-11-02', '22:00:00', '06:00:00');
    app(GeneratePayrollOvertimeEntries::class)->generate($this->employee, '2026-11-02', '2026-11-02');
    expect(PayrollOvertimeEntry::count())->toBe(0);
});

it('uses public-holiday precedence rather than stacking daytime and holiday multipliers', function (): void {
    Holiday::create(['name' => 'Public holiday', 'date' => '2026-11-02', 'counts_as_holiday_overtime' => true, 'is_paid' => true]);
    policyAttendance($this->employee, '2026-11-02', '08:00:00', '10:00:00');
    app(GeneratePayrollOvertimeEntries::class)->generate($this->employee, '2026-11-02', '2026-11-02');
    $entry = PayrollOvertimeEntry::firstOrFail();
    expect($entry->category)->toBe('holiday')->and($entry->minutes)->toBe(120)->and((float) $entry->amount)->toBe(250.0);
});

it('uses assigned rest days rather than assuming only Sunday is a day off', function (): void {
    EmployeeShiftOverride::create(['employee_id' => $this->employee->id, 'date' => '2026-11-05', 'shift_id' => null, 'reason' => 'Substitute rest day']);
    policyAttendance($this->employee, '2026-11-05', '08:00:00', '10:00:00');
    app(GeneratePayrollOvertimeEntries::class)->generate($this->employee, '2026-11-05', '2026-11-05');
    expect(PayrollOvertimeEntry::firstOrFail()->category)->toBe('rest')
        ->and((float) PayrollOvertimeEntry::firstOrFail()->amount)->toBe(200.0);
});

it('calculates money from minutes rather than rounded display hours', function (): void {
    policyAttendance($this->employee, '2026-11-02', '08:00:00', '18:01:00');
    app(GeneratePayrollOvertimeEntries::class)->generate($this->employee, '2026-11-02', '2026-11-02');
    expect(PayrollOvertimeEntry::firstOrFail()->minutes)->toBe(61)
        ->and((float) PayrollOvertimeEntry::firstOrFail()->amount)->toBe(76.25);
});

it('keeps employer pension outside employee gross pay and deductions', function (): void {
    $run = PayrollRun::factory()->create(['period_type' => 'monthly', 'period_start' => '2026-11-01', 'period_end' => '2026-11-30']);
    app(CalculatePayrollRun::class)->handle($run);
    $row = $run->employees()->firstOrFail();
    expect((float) $row->gross_earning)->toBe(12000.0)
        ->and((float) $row->pension_contribution)->toBe(840.0)
        ->and((float) $row->employer_pension_contribution)->toBe(1320.0);
});

it('invalidates payroll input fingerprints when reviewed overtime changes', function (): void {
    policyAttendance($this->employee, '2026-11-02', '08:00:00', '19:00:00');
    app(GeneratePayrollOvertimeEntries::class)->generate($this->employee, '2026-11-02', '2026-11-02');
    $before = PayrollInputs::signature($this->employee, '2026-11-01', '2026-11-30');
    app(ReviewOvertime::class)->handle(PayrollOvertimeEntry::firstOrFail(), true, 'Approved');
    expect(PayrollInputs::signature($this->employee, '2026-11-01', '2026-11-30'))->not->toBe($before);
});

it('resets approval when overtime source times change even if payable minutes stay equal', function (): void {
    $segment = policyAttendance($this->employee, '2026-11-02', '08:00:00', '19:00:00');
    $generator = app(GeneratePayrollOvertimeEntries::class);
    $generator->generate($this->employee, '2026-11-02', '2026-11-02');
    $entry = PayrollOvertimeEntry::firstOrFail();
    app(ReviewOvertime::class)->handle($entry, true, 'Approved');
    $segment->update(['clock_in' => '07:00:00', 'clock_out' => '18:00:00', 'correction_reason' => 'Corrected times']);
    $generator->generate($this->employee, '2026-11-02', '2026-11-02');
    expect($entry->fresh()->minutes)->toBe(120)->and($entry->fresh()->status)->toBe('pending');
});

it('removes formerly approved overtime when its source becomes incomplete', function (): void {
    $segment = policyAttendance($this->employee, '2026-11-02', '08:00:00', '19:00:00');
    $generator = app(GeneratePayrollOvertimeEntries::class);
    $generator->generate($this->employee, '2026-11-02', '2026-11-02');
    $entry = PayrollOvertimeEntry::firstOrFail();
    app(ReviewOvertime::class)->handle($entry, true, 'Approved');
    $segment->update(['clock_out' => null, 'correction_reason' => 'Checkout was invalid']);
    $generator->generate($this->employee, '2026-11-02', '2026-11-02');
    expect($entry->fresh()->status)->toBe('rejected')->and((float) $entry->fresh()->amount)->toBe(0.0);
});

it('flags combined daily and weekly overtime thresholds without discarding worked minutes', function (): void {
    foreach (['2026-11-02', '2026-11-03', '2026-11-04'] as $date) {
        policyAttendance($this->employee, $date, '08:00:00', '23:00:00');
    }
    app(GeneratePayrollOvertimeEntries::class)->generate($this->employee, '2026-11-02', '2026-11-04');
    $entries = PayrollOvertimeEntry::all();
    expect($entries->sum('minutes'))->toBe(1080)
        ->and($entries->every(fn ($entry): bool => $entry->calculation_snapshot['daily_limit_exceeded'] && $entry->calculation_snapshot['weekly_limit_exceeded']))->toBeTrue();
});

it('blocks posting with missing attendance even after review is confirmed', function (): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $run = PayrollRun::factory()->create(['period_type' => 'monthly', 'period_start' => '2026-11-01', 'period_end' => '2026-11-30']);
    app(CalculatePayrollRun::class)->handle($run, [$this->employee->id]);
    $run->update(['attendance_reviewed_at' => now()]);
    expect(fn () => app(PostPayrollRun::class)->handle($run))->toThrow(HrWorkflowException::class, 'Resolve attendance issues');
});

it('posts employee earnings plus employer costs into a balanced journal', function (): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    policyAttendance($this->employee, '2026-11-02', '08:00:00', '17:00:00');
    $run = PayrollRun::factory()->create(['period_type' => 'custom', 'period_start' => '2026-11-02', 'period_end' => '2026-11-02']);
    app(CalculatePayrollRun::class)->handle($run, [$this->employee->id]);
    $run->update(['attendance_reviewed_at' => now()]);
    $journal = app(PostPayrollRun::class)->handle($run);
    $row = $run->employees()->firstOrFail();
    expect((float) $row->gross_earning)->toBe(400.0)
        ->and((float) $journal->total_debit)->toBe(444.0)
        ->and((float) $journal->total_credit)->toBe(444.0);
});

it('retains finalized overtime and rejects direct changes to it', function (): void {
    policyAttendance($this->employee, '2026-11-02', '08:00:00', '19:00:00');
    app(GeneratePayrollOvertimeEntries::class)->generate($this->employee, '2026-11-02', '2026-11-02');
    $entry = PayrollOvertimeEntry::firstOrFail();
    $run = PayrollRun::factory()->create();
    $entry->update(['payroll_run_id' => $run->id]);
    $run->update(['status' => 'approved']);
    expect(fn () => $entry->update(['amount' => 1]))->toThrow(ValidationException::class);
});

it('covers approved partial leave once when the policy deducts lateness', function (): void {
    $policy = PayPolicy::factory()->create(['deduct_lateness' => true]);
    $this->assignment->update(['pay_policy_id' => $policy->id]);
    $type = LeaveType::create(['name' => 'Approved unpaid time', 'is_paid' => false]);
    $this->employee->leaveRequests()->create(['leave_type_id' => $type->id, 'start_date' => '2026-11-02', 'end_date' => '2026-11-02', 'minutes' => 240, 'status' => 'approved']);
    policyAttendance($this->employee, '2026-11-02', '13:00:00', '17:00:00');
    $run = PayrollRun::factory()->create(['period_type' => 'custom', 'period_start' => '2026-11-02', 'period_end' => '2026-11-02']);
    app(CalculatePayrollRun::class)->handle($run, [$this->employee->id]);
    $row = $run->employees()->firstOrFail();
    expect((float) $row->basic_salary)->toBe(200.0)
        ->and((int) $row->calculation_snapshot['unpaid_minutes'])->toBe(240);
});

it('holds unexplained gaps between punch pairs for review', function (): void {
    $segment = policyAttendance($this->employee, '2026-11-02', '08:00:00', '17:00:00');
    $segment->update(['raw_data' => ['work_intervals' => [['2026-11-02 08:00:00', '2026-11-02 10:00:00'], ['2026-11-02 15:00:00', '2026-11-02 17:00:00']]]]);
    $summaries = app(BuildScheduledAttendance::class)->handle($this->employee, '2026-11-02', '2026-11-02');
    expect(implode(' ', $summaries->first()->calculation_snapshot['issues']))->toContain('unrecorded interval');
});

it('shows approved overtime in competing drafts and claims it only for the posted payroll', function (): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    policyAttendance($this->employee, '2026-11-02', '08:00:00', '19:00:00');
    app(GeneratePayrollOvertimeEntries::class)->generate($this->employee, '2026-11-02', '2026-11-02');
    $entry = PayrollOvertimeEntry::firstOrFail();
    app(ReviewOvertime::class)->handle($entry, true, 'Approved overtime');
    $first = PayrollRun::factory()->create(['period_type' => 'custom', 'period_start' => '2026-11-02', 'period_end' => '2026-11-02']);
    $second = PayrollRun::factory()->create(['period_type' => 'custom', 'period_start' => '2026-11-02', 'period_end' => '2026-11-02']);
    app(CalculatePayrollRun::class)->handle($first, [$this->employee->id]);
    app(CalculatePayrollRun::class)->handle($second, [$this->employee->id]);
    expect((float) $first->employees()->firstOrFail()->overtime_amount)->toBe(150.0)
        ->and((float) $second->employees()->firstOrFail()->overtime_amount)->toBe(150.0);
    $first->update(['attendance_reviewed_at' => now()]);
    app(PostPayrollRun::class)->handle($first);
    expect($entry->fresh()->payroll_run_id)->toBe($first->id);
    $second->update(['attendance_reviewed_at' => now()]);
    expect(fn () => app(PostPayrollRun::class)->handle($second))->toThrow(HrWorkflowException::class, 'already covers');
});

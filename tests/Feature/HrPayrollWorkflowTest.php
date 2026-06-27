<?php

use App\Filament\Resources\PayrollRuns\Pages\CreatePayrollRun;
use App\Models\Account;
use App\Models\AttendanceDailySummary;
use App\Models\AttendanceImport;
use App\Models\AttendancePeriodSummary;
use App\Models\AttendanceSegment;
use App\Models\Bank;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\LeaveType;
use App\Models\Payment;
use App\Models\PayrollRun;
use App\Models\PayrollRunEmployee;
use App\Models\PayrollTaxRule;
use App\Models\Shift;
use App\Models\User;
use App\Services\Hr\CalculatePayrollRun;
use App\Services\Hr\ExportPayrollRegisterCsv;
use App\Services\Hr\GeneratePayrollPayments;
use App\Services\Hr\ImportAttendanceSegmentCsv;
use App\Services\Hr\ImportAttendanceSummaryReport;
use App\Services\Hr\PostPayrollRun;
use App\Services\Hr\RecordManualAttendanceLog;
use App\Services\Hr\RepayEmployeeLoan;
use App\UserRole;
use Database\Seeders\PayrollTaxRuleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('registers payroll run resource routes only in the finance panel', function (): void {
    expect(Route::has('filament.finance.resources.payroll-runs.index'))->toBeTrue()
        ->and(Route::has('filament.hr.resources.payroll-runs.index'))->toBeFalse();
});

it('creates a payroll run from the finance filament form without saving blank payroll rows', function () {
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    Livewire::test(CreatePayrollRun::class)
        ->fillForm([
            'name' => 'May payroll',
            'pay_date' => '2026-05-22',
            'period_start' => '2026-04-22',
            'period_end' => '2026-05-22',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $payrollRun = PayrollRun::query()->firstOrFail();

    expect($payrollRun->name)->toBe('May payroll')
        ->and($payrollRun->employees()->count())->toBe(0);
});

it('requires reasons for manual attendance entries', function () {
    $employee = Employee::create([
        'employee_id' => 'EMP-0000',
        'attendance_device_id' => '1',
        'first_name' => 'Test',
        'last_name' => 'Employee',
        'phone' => '0911000009',
        'hire_date' => '2018-01-01',
        'status' => 'Active',
        'employment_type' => 'permanent',
        'basic_salary' => 10000,
    ]);

    app(RecordManualAttendanceLog::class)->handle($employee, '2026-05-20 08:31:00', 'in', '');
})->throws(RuntimeException::class);

it('imports normal and night summary reports and keeps unmatched rows as failures', function () {
    $employee = Employee::create([
        'employee_id' => 'EMP-0001',
        'attendance_device_id' => '3',
        'first_name' => 'Abdulkerim',
        'last_name' => 'Amdega',
        'phone' => '0911000000',
        'hire_date' => '2018-01-01',
        'status' => 'active',
        'basic_salary' => 12000,
        'overtime_multiplier' => 1,
    ]);

    $normalPath = storage_path('app/normal-attendance.csv');
    $nightPath = storage_path('app/night-attendance.csv');

    file_put_contents($normalPath, crystalReportCsv('3', $employee->full_name, '08:38'));
    file_put_contents($nightPath, crystalReportCsv('999', 'Unknown Employee', '05:56'));

    app(ImportAttendanceSummaryReport::class)->handle($normalPath, 'normal');
    app(ImportAttendanceSummaryReport::class)->handle($nightPath, 'night');

    expect(AttendanceImport::query()->count())->toBe(2)
        ->and(AttendancePeriodSummary::query()->count())->toBe(1)
        ->and(AttendanceImport::query()->where('shift_type', 'night')->first()->failed_rows)->toBe(1);
});

it('imports one attendance csv as segments and builds daily summaries', function () {
    $employee = Employee::create([
        'employee_id' => 'EMP-0010',
        'attendance_device_id' => '22',
        'first_name' => 'Abas',
        'last_name' => 'Mohammed',
        'phone' => '0911000010',
        'hire_date' => '2018-01-01',
        'status' => 'Active',
        'basic_salary' => 12000,
        'overtime_multiplier' => 1,
    ]);
    $nightEmployee = Employee::create([
        'employee_id' => 'EMP-0011',
        'attendance_device_id' => '9',
        'first_name' => 'Night',
        'last_name' => 'Worker',
        'phone' => '0911000011',
        'hire_date' => '2018-01-01',
        'status' => 'Active',
        'basic_salary' => 10000,
        'overtime_multiplier' => 1,
    ]);
    $path = storage_path('app/attendance-segments.csv');

    file_put_contents($path, attendanceSegmentCsv());

    app(ImportAttendanceSegmentCsv::class)->handle($path);

    $summary = AttendanceDailySummary::query()
        ->where('employee_id', $employee->id)
        ->whereDate('date', '2026-05-04')
        ->firstOrFail();
    $nightSummary = AttendanceDailySummary::query()
        ->where('employee_id', $nightEmployee->id)
        ->whereDate('date', '2026-05-04')
        ->firstOrFail();

    expect(AttendanceSegment::query()->count())->toBe(4)
        ->and(Schema::hasColumn('attendance_segments', 'employee_code'))->toBeFalse()
        ->and(Schema::hasColumn('attendance_segments', 'employee_name'))->toBeFalse()
        ->and(Schema::hasColumn('attendance_segments', 'shift_type'))->toBeFalse()
        ->and(Schema::hasColumn('attendance_daily_summaries', 'work_schedule_id'))->toBeFalse()
        ->and(Schema::hasColumn('attendance_daily_summaries', 'shift_id'))->toBeFalse()
        ->and(Schema::hasColumn('attendance_segments', 'shift_id'))->toBeTrue()
        ->and(Schema::hasTable('shifts'))->toBeTrue()
        ->and(Schema::hasTable('work_schedules'))->toBeFalse()
        ->and(Shift::query()->where('name', 'Morning(Shift)')->exists())->toBeTrue()
        ->and(AttendanceSegment::query()->where('schedule_name', 'Morning(Shift)')->firstOrFail()->shift_id)->not->toBeNull()
        ->and($summary->expected_minutes)->toBe(480)
        ->and($summary->worked_minutes)->toBe(493)
        ->and($summary->status)->toBe('present')
        ->and($nightSummary->status)->toBe('partial')
        ->and(AttendanceSegment::query()->where('employee_id', $nightEmployee->id)->firstOrFail()->clock_out)->toBe('03:00:00')
        ->and($nightSummary->calculation_snapshot)->not->toHaveKey('shift_types');
});

it('uses matched shift times when imported attendance is missing one clock value', function () {
    $employee = Employee::create([
        'employee_id' => 'EMP-0011',
        'attendance_device_id' => '44',
        'first_name' => 'Clock',
        'last_name' => 'Fallback',
        'phone' => '0911000011',
        'hire_date' => '2026-01-01',
        'status' => 'active',
        'basic_salary' => 12000,
        'overtime_multiplier' => 1,
    ]);

    Shift::create([
        'name' => 'Morning(Shift)',
        'start_time' => '08:00:00',
        'end_time' => '12:30:00',
        'expected_minutes' => 270,
    ]);

    $path = storage_path('app/attendance-clock-fallback.csv');

    file_put_contents($path, implode("\n", [
        'FP No,Emp Code,FullName,Date,Schedule,On Duty,Off Duty,Clock In,Clock Out,Late(M),Early(M),Status,OT,OT Hrs,OT In,OT Out,Exception,Count,M-In,M-Out,In Day(Hr),mod',
        '44,I-44,Clock Fallback,05-04-26,Morning(Shift),,,8:05 AM,,,,,,,,,,0.5,1,1,,0',
    ]));

    app(ImportAttendanceSegmentCsv::class)->handle($path);

    $segment = AttendanceSegment::query()
        ->where('employee_id', $employee->id)
        ->firstOrFail();

    expect($segment->shift_id)->not->toBeNull()
        ->and($segment->scheduled_start)->toBe('08:00:00')
        ->and($segment->scheduled_end)->toBe('12:30:00')
        ->and($segment->clock_out)->toBe('12:30:00')
        ->and($segment->worked_minutes)->toBe(265);
});

it('calculates payroll from imported attendance segments', function () {
    $this->seed(PayrollTaxRuleSeeder::class);

    $employee = Employee::create([
        'employee_id' => 'EMP-0012',
        'attendance_device_id' => '22',
        'first_name' => 'Segment',
        'last_name' => 'Payroll',
        'phone' => '0911000012',
        'hire_date' => '2026-01-01',
        'status' => 'Active',
        'basic_salary' => 12000,
        'overtime_multiplier' => 1,
    ]);
    $path = storage_path('app/attendance-segment-payroll.csv');

    file_put_contents($path, attendanceSegmentCsv());

    app(ImportAttendanceSegmentCsv::class)->handle($path);

    $payrollRun = PayrollRun::create([
        'name' => 'Segment payroll',
        'period_start' => '2026-05-01',
        'period_end' => '2026-05-04',
    ]);

    app(CalculatePayrollRun::class)->handle($payrollRun, [$employee->id]);

    $payrollEmployee = $payrollRun->employees()->firstOrFail();

    expect($payrollEmployee->calculation_snapshot['daily_summary_ids'])->not->toBeEmpty()
        ->and((float) $payrollEmployee->net_pay)->toBeGreaterThan(0);
});

it('calculates batch payroll from merged attendance summaries and locks recalculation after approval', function () {
    $this->seed(PayrollTaxRuleSeeder::class);

    $employee = Employee::create([
        'employee_id' => 'EMP-0002',
        'attendance_device_id' => '77',
        'first_name' => 'Abdulhamid',
        'last_name' => 'Mekonn',
        'phone' => '0911000001',
        'hire_date' => '2018-01-01',
        'status' => 'Active',
        'employment_type' => 'permanent',
        'basic_salary' => 12000,
        'overtime_multiplier' => 1.25,
        'pension_enabled' => true,
    ]);

    AttendancePeriodSummary::create([
        'employee_id' => $employee->id,
        'period_start' => '2018-08-25',
        'period_end' => '2018-09-12',
        'shift_type' => 'normal',
        'work_days' => 14,
        'actual_days' => 12.5,
        'absent_days' => 0.5,
        'late_minutes' => 76,
        'early_minutes' => 720,
        'overtime_minutes' => 299,
        'holiday_days' => 1,
        'work_time_hours' => 100,
    ]);

    AttendancePeriodSummary::create([
        'employee_id' => $employee->id,
        'period_start' => '2018-08-25',
        'period_end' => '2018-09-12',
        'shift_type' => 'night',
        'work_days' => 14,
        'actual_days' => 1,
        'absent_days' => 0,
        'late_minutes' => 0,
        'early_minutes' => 0,
        'overtime_minutes' => 60,
        'holiday_days' => 0,
        'dayoff_days' => 1,
        'work_time_hours' => 8,
    ]);

    $payrollRun = PayrollRun::create([
        'name' => 'August payroll',
        'period_start' => '2018-08-25',
        'period_end' => '2018-09-12',
        'pay_date' => '2018-09-13',
    ]);

    app(CalculatePayrollRun::class)->handle($payrollRun);

    $payrollEmployee = $payrollRun->employees()->firstOrFail();

    expect($payrollRun->refresh()->status)->toBe('draft')
        ->and((float) $payrollEmployee->overtime_amount)->toBeGreaterThan(0)
        ->and((float) $payrollEmployee->income_tax)->toBeGreaterThan(0)
        ->and((float) $payrollEmployee->net_pay)->toBeGreaterThan(0);

    app(PostPayrollRun::class)->handle($payrollRun);

    app(CalculatePayrollRun::class)->handle($payrollRun);
})->throws(RuntimeException::class);

it('uses the employee transportation allowance as the payroll default', function () {
    $this->seed(PayrollTaxRuleSeeder::class);

    $employee = Employee::create([
        'employee_id' => 'EMP-TRAVEL-1',
        'attendance_device_id' => '701',
        'first_name' => 'Transit',
        'last_name' => 'Allowance',
        'phone' => '0911555701',
        'hire_date' => '2026-01-01',
        'status' => 'active',
        'basic_salary' => 12000,
        'transport_allowance' => 150,
        'pension_enabled' => false,
    ]);

    $payrollRun = PayrollRun::create([
        'name' => 'Transport allowance payroll',
        'period_start' => '2026-05-01',
        'period_end' => '2026-05-31',
    ]);

    app(CalculatePayrollRun::class)->handle($payrollRun, [$employee->id]);

    $payrollEmployee = $payrollRun->employees()->firstOrFail();

    expect((float) $payrollEmployee->transport_allowance)->toBe(150.0)
        ->and((float) $payrollEmployee->gross_earning)->toBe((float) $payrollEmployee->basic_salary + 150.0);
});

it('matches the finance payroll register formula and exports the register csv', function () {
    $rule = PayrollTaxRule::create([
        'name' => 'Finance sample PAYE',
        'jurisdiction' => 'ET',
        'effective_from' => '2010-01-01',
        'is_active' => true,
    ]);
    $rule->brackets()->create([
        'min_income' => 0,
        'max_income' => null,
        'rate' => 35,
        'deduction' => 2050,
    ]);

    $employee = Employee::create([
        'employee_id' => 'EMP-FIN-1',
        'attendance_device_id' => '501',
        'first_name' => 'Abas',
        'last_name' => 'Seman',
        'phone' => '0911555001',
        'hire_date' => '2026-01-01',
        'status' => 'active',
        'position' => 'Bin. Dep.Hea.',
        'basic_salary' => 15432,
        'overtime_multiplier' => 1,
        'pension_enabled' => true,
    ]);

    $loan = $employee->loans()->create([
        'loan_date' => '2026-05-03',
        'return_date' => '2026-05-31',
        'amount' => 3750,
        'reason' => 'Monthly loan deduction',
        'status' => 'active',
    ]);

    AttendancePeriodSummary::create([
        'employee_id' => $employee->id,
        'period_start' => '2026-05-01',
        'period_end' => '2026-05-31',
        'shift_type' => 'normal',
        'work_days' => 26,
        'actual_days' => 26,
        'absent_days' => 0,
        'late_minutes' => 0,
        'early_minutes' => 0,
        'overtime_minutes' => 2684,
        'work_time_hours' => 208,
    ]);

    $payrollRun = PayrollRun::create([
        'name' => 'Finance sample payroll',
        'period_start' => '2026-05-01',
        'period_end' => '2026-05-31',
    ]);

    app(CalculatePayrollRun::class)->handle($payrollRun, [$employee->id]);

    $payrollEmployee = $payrollRun->employees()->firstOrFail();
    $csv = app(ExportPayrollRegisterCsv::class)->csv($payrollRun->refresh());

    expect((float) $payrollEmployee->basic_salary)->toBe(15432.0)
        ->and((float) $payrollEmployee->pay_per_hour)->toBe(64.3)
        ->and((float) $payrollEmployee->overtime_hours)->toBe(44.73)
        ->and((float) $payrollEmployee->overtime_amount)->toBe(3595.17)
        ->and((float) $payrollEmployee->employer_pension_contribution)->toBe(1697.52)
        ->and((float) $payrollEmployee->gross_earning)->toBe(20724.69)
        ->and((float) $payrollEmployee->taxable_amount)->toBe(19027.17)
        ->and((float) $payrollEmployee->income_tax)->toBe(4609.51)
        ->and((float) $payrollEmployee->pension_contribution)->toBe(2777.76)
        ->and((float) $payrollEmployee->loan)->toBe(3750.0)
        ->and((float) $payrollEmployee->workers_union)->toBe(154.32)
        ->and((float) $payrollEmployee->total_deduction)->toBe(11291.59)
        ->and((float) $payrollEmployee->net_pay)->toBe(9433.1)
        ->and($payrollEmployee->calculation_snapshot['loan_ids'])->toContain($loan->id)
        ->and($payrollEmployee->calculation_snapshot['loan_installment_ids'])->toContain($loan->installments()->firstOrFail()->id)
        ->and($csv)->toContain('Employer Pension')
        ->and($csv)->toContain('9433.10');
});

it('deducts only due loan installments and closes them when payroll is posted', function () {
    $this->seed(PayrollTaxRuleSeeder::class);

    $employee = Employee::create([
        'employee_id' => 'EMP-LOAN-1',
        'attendance_device_id' => '601',
        'first_name' => 'Loan',
        'last_name' => 'Installment',
        'phone' => '0911555601',
        'hire_date' => '2026-01-01',
        'status' => 'active',
        'basic_salary' => 12000,
        'overtime_multiplier' => 1,
    ]);

    $loan = $employee->loans()->create([
        'loan_date' => '2026-05-01',
        'return_date' => '2026-05-31',
        'amount' => 1200,
        'installment_count' => 3,
        'reason' => 'Three month repayment',
        'status' => 'active',
    ]);

    $payrollRun = PayrollRun::create([
        'name' => 'Installment payroll',
        'period_start' => '2026-05-01',
        'period_end' => '2026-05-31',
    ]);

    app(CalculatePayrollRun::class)->handle($payrollRun, [$employee->id]);

    $payrollEmployee = $payrollRun->employees()->firstOrFail();
    $firstInstallment = $loan->installments()->orderBy('due_date')->firstOrFail();

    expect($loan->installments()->count())->toBe(3)
        ->and((float) $payrollEmployee->loan)->toBe(400.0)
        ->and($payrollEmployee->calculation_snapshot['loan_installment_ids'])->toBe([$firstInstallment->id]);

    app(PostPayrollRun::class)->handle($payrollRun);

    expect($firstInstallment->refresh()->status)->toBe('paid')
        ->and((float) $firstInstallment->paid_amount)->toBe(400.0)
        ->and($loan->refresh()->status)->toBe('partially_paid')
        ->and($loan->installments()->where('status', 'pending')->count())->toBe(2);
});

it('posts payroll approval to salary overtime pension and deduction accounts', function () {
    $payrollRun = PayrollRun::create([
        'name' => 'Posting split payroll',
        'period_start' => '2026-05-01',
        'period_end' => '2026-05-31',
    ]);
    $employee = Employee::create([
        'employee_id' => 'EMP-POST-1',
        'attendance_device_id' => 'POST-1',
        'first_name' => 'Posting',
        'last_name' => 'Split',
        'phone' => '0911555604',
        'hire_date' => '2026-01-01',
        'status' => 'active',
        'basic_salary' => 12000,
    ]);

    $payrollEmployee = PayrollRunEmployee::withoutEvents(fn () => $payrollRun->employees()->create([
        'employee_id' => $employee->id,
        'basic_salary' => 1000,
        'overtime_amount' => 200,
        'employer_pension_contribution' => 110,
        'gross_earning' => 1310,
        'income_tax' => 100,
        'penalty_amount' => 10,
        'pension_contribution' => 180,
        'loan' => 20,
        'workers_union' => 0,
        'total_deduction' => 310,
        'net_pay' => 1000,
    ]));

    $entry = app(PostPayrollRun::class)->handle($payrollRun);

    expect((float) $entry->total_debit)->toBe(1310.0)
        ->and((float) $entry->total_credit)->toBe(1310.0)
        ->and((float) $entry->journalItems()->whereBelongsTo(Account::where('code', '5100')->firstOrFail())->firstOrFail()->debit)->toBe(1000.0)
        ->and((float) $entry->journalItems()->whereBelongsTo(Account::where('code', '5110')->firstOrFail())->firstOrFail()->debit)->toBe(200.0)
        ->and((float) $entry->journalItems()->whereBelongsTo(Account::where('code', '5120')->firstOrFail())->firstOrFail()->debit)->toBe(110.0)
        ->and((float) $entry->journalItems()->whereBelongsTo(Account::where('code', '2160')->firstOrFail())->firstOrFail()->credit)->toBe(100.0)
        ->and((float) $entry->journalItems()->whereBelongsTo(Account::where('code', '2170')->firstOrFail())->firstOrFail()->credit)->toBe(180.0)
        ->and((float) $entry->journalItems()->whereBelongsTo(Account::where('code', '1230')->firstOrFail())->firstOrFail()->credit)->toBe(20.0);

    expect($payrollEmployee->refresh()->payment_id)->toBeNull();
});

it('records manual employee loan repayments through payments and journals', function () {
    $employee = Employee::create([
        'employee_id' => 'EMP-LOAN-2',
        'attendance_device_id' => '602',
        'first_name' => 'Manual',
        'last_name' => 'Repayment',
        'phone' => '0911555602',
        'hire_date' => '2026-01-01',
        'status' => 'active',
        'basic_salary' => 12000,
    ]);

    $loan = $employee->loans()->create([
        'loan_date' => '2026-05-01',
        'return_date' => '2026-05-31',
        'amount' => 900,
        'installment_count' => 3,
        'reason' => 'Manual repayment',
        'status' => 'active',
    ]);

    $payment = app(RepayEmployeeLoan::class)->handle($loan, 'cash');
    $journal = JournalEntry::query()
        ->where('source_type', Payment::class)
        ->where('source_id', $payment->id)
        ->firstOrFail();
    $loanAccount = Account::query()->where('code', '1230')->firstOrFail();

    expect((float) $payment->amount)->toBe(900.0)
        ->and($payment->transaction_type)->toBe('employee_loan_repayment')
        ->and($loan->refresh()->status)->toBe('deducted')
        ->and($loan->installments()->where('status', 'paid')->count())->toBe(3)
        ->and((float) $journal->journalItems()->where('account_id', $loanAccount->id)->firstOrFail()->credit)->toBe(900.0);
});

it('supports partial manual employee loan repayments and keeps the remaining balance for payroll', function () {
    $this->seed(PayrollTaxRuleSeeder::class);

    $employee = Employee::create([
        'employee_id' => 'EMP-LOAN-3',
        'attendance_device_id' => '603',
        'first_name' => 'Partial',
        'last_name' => 'Repayment',
        'phone' => '0911555603',
        'hire_date' => '2026-01-01',
        'status' => 'active',
        'basic_salary' => 12000,
    ]);

    $loan = $employee->loans()->create([
        'loan_date' => '2026-05-01',
        'return_date' => '2026-05-31',
        'amount' => 900,
        'installment_count' => 3,
        'reason' => 'Partial repayment',
        'status' => 'active',
    ]);

    $payment = app(RepayEmployeeLoan::class)->handle($loan, 'cash', null, 100.0);
    $firstInstallment = $loan->installments()->orderBy('due_date')->firstOrFail();

    expect((float) $payment->amount)->toBe(100.0)
        ->and($loan->refresh()->status)->toBe('partially_paid')
        ->and((float) $firstInstallment->refresh()->paid_amount)->toBe(100.0)
        ->and($firstInstallment->status)->toBe('pending')
        ->and((float) $loan->remainingBalance())->toBe(800.0);

    $payrollRun = PayrollRun::create([
        'name' => 'Partial repayment payroll',
        'period_start' => '2026-05-01',
        'period_end' => '2026-05-31',
    ]);

    app(CalculatePayrollRun::class)->handle($payrollRun, [$employee->id]);

    $payrollEmployee = $payrollRun->employees()->firstOrFail();

    expect((float) $payrollEmployee->loan)->toBe(200.0)
        ->and($loan->refresh()->status)->toBe('partially_paid');

    app(PostPayrollRun::class)->handle($payrollRun);

    expect($firstInstallment->refresh()->status)->toBe('paid')
        ->and((float) $firstInstallment->paid_amount)->toBe(300.0)
        ->and($loan->refresh()->status)->toBe('partially_paid')
        ->and((float) $loan->remainingBalance())->toBe(600.0);
});

it('applies paid and unpaid leave to payroll absence deductions', function () {
    $this->seed(PayrollTaxRuleSeeder::class);

    $employee = Employee::create([
        'employee_id' => 'EMP-0004',
        'attendance_device_id' => '10',
        'first_name' => 'Leave',
        'last_name' => 'Tester',
        'phone' => '0911000004',
        'hire_date' => '2018-01-01',
        'status' => 'active',
        'basic_salary' => 10000,
    ]);

    $paidLeave = LeaveType::create(['name' => 'Annual', 'is_paid' => true]);
    $unpaidLeave = LeaveType::create(['name' => 'Unpaid', 'is_paid' => false]);

    $employee->leaveRequests()->create([
        'leave_type_id' => $paidLeave->id,
        'start_date' => '2018-08-26',
        'end_date' => '2018-08-26',
        'status' => 'approved',
    ]);

    $employee->leaveRequests()->create([
        'leave_type_id' => $unpaidLeave->id,
        'start_date' => '2018-08-27',
        'end_date' => '2018-08-27',
        'status' => 'approved',
    ]);

    AttendancePeriodSummary::create([
        'employee_id' => $employee->id,
        'period_start' => '2018-08-25',
        'period_end' => '2018-09-12',
        'shift_type' => 'normal',
        'work_days' => 10,
        'actual_days' => 8,
        'absent_days' => 2,
    ]);

    $payrollRun = PayrollRun::create([
        'name' => 'Leave payroll',
        'period_start' => '2018-08-25',
        'period_end' => '2018-09-12',
    ]);

    app(CalculatePayrollRun::class)->handle($payrollRun);

    $payrollEmployee = $payrollRun->employees()->firstOrFail();

    expect((float) $payrollEmployee->penalty_amount)->toBe(333.33)
        ->and((int) $payrollEmployee->calculation_snapshot['paid_leave_minutes'])->toBe(480)
        ->and((int) $payrollEmployee->calculation_snapshot['unpaid_leave_minutes'])->toBe(480);
});

it('pro-rates salary for employees hired during the payroll period', function () {
    $this->seed(PayrollTaxRuleSeeder::class);

    $employee = Employee::create([
        'employee_id' => 'EMP-PRORATE-1',
        'attendance_device_id' => '702',
        'first_name' => 'Prorated',
        'last_name' => 'Hire',
        'phone' => '0911777002',
        'hire_date' => '2026-05-16',
        'status' => 'active',
        'basic_salary' => 26000,
        'overtime_multiplier' => 1,
    ]);

    $payrollRun = PayrollRun::create([
        'name' => 'Prorated payroll',
        'period_start' => '2026-05-01',
        'period_end' => '2026-05-31',
    ]);

    app(CalculatePayrollRun::class)->handle($payrollRun, [$employee->id]);

    $payrollEmployee = $payrollRun->employees()->firstOrFail();

    expect((float) $payrollEmployee->basic_salary)->toBe(13000.0)
        ->and((float) $payrollEmployee->calculation_snapshot['full_basic_salary'])->toBe(26000.0)
        ->and($payrollEmployee->calculation_snapshot['employment_business_days'])->toBe(13)
        ->and($payrollEmployee->calculation_snapshot['period_business_days'])->toBe(26);
});

it('does not double count unpaid leave and attendance absence on the same day', function () {
    $this->seed(PayrollTaxRuleSeeder::class);

    $employee = Employee::create([
        'employee_id' => 'EMP-LEAVE-DAILY',
        'attendance_device_id' => '703',
        'first_name' => 'Daily',
        'last_name' => 'Leave',
        'phone' => '0911777003',
        'hire_date' => '2026-01-01',
        'status' => 'active',
        'basic_salary' => 10000,
        'overtime_multiplier' => 1,
    ]);
    $unpaidLeave = LeaveType::create(['name' => 'Unpaid daily', 'is_paid' => false]);

    AttendanceDailySummary::create([
        'employee_id' => $employee->id,
        'date' => '2026-05-04',
        'expected_minutes' => 480,
        'worked_minutes' => 0,
        'absence_minutes' => 480,
        'status' => 'absent',
    ]);

    $employee->leaveRequests()->create([
        'leave_type_id' => $unpaidLeave->id,
        'start_date' => '2026-05-04',
        'end_date' => '2026-05-04',
        'status' => 'approved',
    ]);

    $payrollRun = PayrollRun::create([
        'name' => 'Daily leave payroll',
        'period_start' => '2026-05-01',
        'period_end' => '2026-05-31',
    ]);

    app(CalculatePayrollRun::class)->handle($payrollRun, [$employee->id]);

    $payrollEmployee = $payrollRun->employees()->firstOrFail();

    expect((float) $payrollEmployee->penalty_hours)->toBe(8.0)
        ->and((float) $payrollEmployee->penalty_amount)->toBe(333.33)
        ->and((int) $payrollEmployee->calculation_snapshot['deductible_absent_minutes'])->toBe(480);
});

it('does not calculate an empty payroll run when no active employees exist', function () {
    $this->seed(PayrollTaxRuleSeeder::class);

    PayrollRun::create([
        'name' => 'Empty payroll',
        'period_start' => '2018-08-25',
        'period_end' => '2018-09-12',
    ]);

    app(CalculatePayrollRun::class)->handle(PayrollRun::firstOrFail());
})->throws(RuntimeException::class, 'No active employees');

it('posts payroll journals and generates outbound payroll payments', function () {
    $this->seed(PayrollTaxRuleSeeder::class);
    $bank = Bank::create([
        'name' => 'Payroll Bank',
        'code' => 'PAYROLL-BANK',
        'account_number' => '1234567890',
        'account_holder_name' => 'Printos',
        'bank_name' => 'Payroll Bank S.C.',
        'current_balance' => 100000,
        'status' => 'active',
    ]);

    $employee = Employee::create([
        'employee_id' => 'EMP-0003',
        'attendance_device_id' => '9',
        'first_name' => 'Abdulhalim',
        'last_name' => 'Abubeker',
        'phone' => '0911000002',
        'hire_date' => '2018-01-01',
        'status' => 'active',
        'basic_salary' => 10000,
        'overtime_multiplier' => 1,
    ]);
    $secondEmployee = Employee::create([
        'employee_id' => 'EMP-0005',
        'attendance_device_id' => '11',
        'first_name' => 'Second',
        'last_name' => 'Payroll',
        'phone' => '0911000005',
        'hire_date' => '2018-01-01',
        'status' => 'active',
        'basic_salary' => 8000,
        'overtime_multiplier' => 1,
    ]);

    AttendancePeriodSummary::create([
        'employee_id' => $employee->id,
        'period_start' => '2018-08-25',
        'period_end' => '2018-09-12',
        'shift_type' => 'night',
        'work_days' => 16,
        'actual_days' => 8,
        'absent_days' => 0,
        'late_minutes' => 150,
        'early_minutes' => 0,
        'overtime_minutes' => 356,
        'holiday_days' => 1,
        'dayoff_days' => 1,
        'work_time_hours' => 64,
    ]);
    AttendancePeriodSummary::create([
        'employee_id' => $secondEmployee->id,
        'period_start' => '2018-08-25',
        'period_end' => '2018-09-12',
        'shift_type' => 'normal',
        'work_days' => 16,
        'actual_days' => 16,
        'absent_days' => 0,
        'late_minutes' => 0,
        'early_minutes' => 0,
        'overtime_minutes' => 0,
        'holiday_days' => 0,
        'dayoff_days' => 0,
        'work_time_hours' => 128,
    ]);

    $payrollRun = PayrollRun::create([
        'name' => 'Night payroll',
        'period_start' => '2018-08-25',
        'period_end' => '2018-09-12',
        'pay_date' => '2018-09-13',
    ]);

    app(CalculatePayrollRun::class)->handle($payrollRun);

    $journalEntry = app(PostPayrollRun::class)->handle($payrollRun);
    $netPayTotal = (float) $payrollRun->employees()->sum('net_pay');
    app(GeneratePayrollPayments::class)->handle($payrollRun, 'bank', $bank->id);

    $payment = Payment::query()->where('payment_type', 'payroll')->firstOrFail();
    $paymentJournal = JournalEntry::query()
        ->where('source_type', Payment::class)
        ->where('source_id', $payment->id)
        ->firstOrFail();
    $payrollPayable = Account::query()->where('code', '2150')->firstOrFail();
    $bankAccount = Account::query()->where('code', '1010')->firstOrFail();

    expect($journalEntry->total_debit)->toEqual($journalEntry->total_credit)
        ->and(JournalEntry::query()->where('source_type', PayrollRun::class)->count())->toBe(1)
        ->and(Payment::query()->where('payment_type', 'payroll')->count())->toBe(1)
        ->and((float) $payment->amount)->toBe($netPayTotal)
        ->and($payment->reference)->toBe('Payroll for 2018-08-25 - 2018-09-12')
        ->and($payment->method)->toBe('bank')
        ->and($payment->bank_id)->toBe($bank->id)
        ->and($payrollRun->employees()->where('payment_id', $payment->id)->count())->toBe(2)
        ->and((float) $bank->fresh()->current_balance)->toBe(round(100000 - (float) $payment->amount, 2))
        ->and((float) $paymentJournal->journalItems()->where('account_id', $payrollPayable->id)->firstOrFail()->debit)->toBe((float) $payment->amount)
        ->and((float) $paymentJournal->journalItems()->where('account_id', $bankAccount->id)->firstOrFail()->credit)->toBe((float) $payment->amount)
        ->and($payrollRun->refresh()->status)->toBe('paid');
});

function crystalReportCsv(string $acNo, string $fullName, string $overtime): string
{
    return implode("\n", [
        'Nejashi Printing Press PLC,,,,,,,,,,,,,,,,,,,,,,,,,,,',
        'General Attendance Statstics,,,,,,,,,,,,,,,,,,,,,,,,,,,',
        ',,,,,Date Range :- 25/08/2018 - 12/09/2018,,,,,,,,,,,,,,,,Print Date :,,,05-08-26,,,',
        ',,,,,,,,,,,,,,,,,,,,,,,,,,,',
        "\"S.No\",\"Full Name\",\"AC No\",\"Department\",\"Division\",,\" Work Days\",\"Actual Days\",\"Absent  Days\",\"Late Minutes\",\"Early Minutes\",\"Lunch\nMinutes\",\"Total OT\n(HH:MM)\",\"Holiday\",\"Leave \",,\"Modifide\",,,\" Leave On Bus\",\" Dayoff\",,\"Work Time\n Hrs\",\"Work \n%\",,,\"Termination Date\",",
        ',,,,,,,,,,,,,,,,In,,out,,,,,,,,,',
        "1 ,{$fullName},{$acNo},Production,Center,,14.00,12.50,0.50,76.00,720.00,0.00,{$overtime},1.00,0.00,,0.00,,0.00,0.00,1.00,,100.00,89.29,,,,",
    ]);
}

function attendanceSegmentCsv(): string
{
    return implode("\n", [
        'FP No,Emp Code,FullName,Date,Schedule,On Duty,Off Duty,Clock In,Clock Out,Late(M),Early(M),Status,OT,OT Hrs,OT In,OT Out,Exception,Count,M-In,M-Out,In Day(Hr),mod',
        '22,I-1,Abas Seman mohammed,05-01-26,,,,,,,,Holiday,,,,,Holiday,1,,,,0',
        '22,I-1,Abas Seman mohammed,05-04-26,Morning(Shift),8:00 AM,12:30 AM,7:53 AM,12:34 AM,,,,,,,,,0.5,1,1,4:41,0',
        '22,I-1,Abas Seman mohammed,05-04-26,After noon(Shift),1:30 PM,5:00 PM,1:32 PM,5:04 PM,,,,,,,,,0.5,1,1,3:32,0',
        '9,I-32,Night Worker,05-04-26,1-9 night,7:00 PM,3:00 AM,6:55 PM,,,240,Early,,,,,Early,1,1,,0,0',
    ]);
}

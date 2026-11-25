<?php

use App\Filament\Pages\Settings;
use App\Filament\Resources\OvertimeRules\OvertimeRuleResource;
use App\Filament\Resources\PayPolicies\Pages\ManagePayPolicies;
use App\Filament\Resources\PayrollRuns\Pages\CreatePayrollRun;
use App\Filament\Resources\PayrollRuns\Pages\EditPayrollRun;
use App\Filament\Resources\PayrollRuns\Pages\ListPayrollRuns;
use App\Models\Account;
use App\Models\AttendanceDailySummary;
use App\Models\AttendanceImport;
use App\Models\AttendancePeriodSummary;
use App\Models\AttendanceSegment;
use App\Models\Bank;
use App\Models\Employee;
use App\Models\EmployeeShiftOverride;
use App\Models\Holiday;
use App\Models\JournalEntry;
use App\Models\LeaveType;
use App\Models\Payment;
use App\Models\PayrollOvertimeEntry;
use App\Models\PayrollRun;
use App\Models\PayrollRunEmployee;
use App\Models\PayrollTaxRule;
use App\Models\Shift;
use App\Models\User;
use App\Services\Hr\CalculatePayrollRun;
use App\Services\Hr\ExportPayrollBankAdvice;
use App\Services\Hr\ExportPayrollRegisterCsv;
use App\Services\Hr\GeneratePayrollOvertimeEntries;
use App\Services\Hr\GeneratePayrollPayments;
use App\Services\Hr\ImportAttendanceSegmentCsv;
use App\Services\Hr\ImportAttendanceSummaryReport;
use App\Services\Hr\ManagePayrollPaymentBatch;
use App\Services\Hr\PostPayrollRun;
use App\Services\Hr\PrepareMonthlyPayrollRun;
use App\Services\Hr\RecordManualAttendanceLog;
use App\Services\Hr\RepayEmployeeLoan;
use App\Support\Money;
use App\UserRole;
use Database\Seeders\HrWorkSetupSeeder;
use Database\Seeders\OvertimeRuleSeeder;
use Database\Seeders\PayrollTaxRuleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Employee::created(fn (Employee $employee) => seedEmployeePayrollWork($employee));
    AttendancePeriodSummary::created(function (AttendancePeriodSummary $summary): void {
        seedPayrollPeriodPunches($summary->employee, $summary->period_start->toDateString(), $summary->period_end->toDateString());
    });
});

it('registers payroll run resource routes in finance and admin but not HR', function (): void {
    expect(Route::has('filament.finance.resources.payroll-runs.index'))->toBeTrue()
        ->and(Route::has('filament.admin.resources.payroll-runs.index'))->toBeTrue()
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
            'period_type' => 'custom',
            'pay_date' => '2026-05-22',
            'period_range' => '2026-04-22 - 2026-05-22',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $payrollRun = PayrollRun::query()->firstOrFail();

    expect($payrollRun->name)->toBe('Payroll: Apr 22 - May 22, 2026')
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
        ->and(Schema::hasTable('work_schedules'))->toBeTrue()
        ->and(Shift::query()->where('name', 'Morning(Shift)')->exists())->toBeTrue()
        ->and(AttendanceSegment::query()->where('schedule_name', 'Morning(Shift)')->firstOrFail()->shift_id)->not->toBeNull()
        ->and($summary->expected_minutes)->toBe(480)
        ->and($summary->worked_minutes)->toBe(480)
        ->and($summary->status)->toBe('present')
        ->and($nightSummary->status)->toBe('needs_review')
        ->and(AttendanceSegment::query()->where('employee_id', $nightEmployee->id)->firstOrFail()->clock_out)->toBeNull()
        ->and($nightSummary->calculation_snapshot)->not->toHaveKey('shift_types');
});

it('preserves missing clock values for attendance review', function () {
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
        ->and($segment->clock_out)->toBeNull()
        ->and($segment->worked_minutes)->toBe(0);
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

    expect($payrollEmployee->calculation_snapshot['attendance'])->not->toBeEmpty()
        ->and((float) $payrollEmployee->net_pay)->toBeGreaterThan(0);
});

it('shows late attendance without applying a second penalty to monthly salary', function (): void {
    $this->seed(PayrollTaxRuleSeeder::class);
    $employee = Employee::factory()->create(['basic_salary' => 10000]);
    AttendanceSegment::create(['employee_id' => $employee->id, 'date' => '2026-05-04', 'fp_no' => $employee->attendance_device_id, 'clock_in' => '08:10:00', 'clock_out' => '16:55:00', 'status' => 'Present']);
    $run = PayrollRun::factory()->create(['period_start' => '2026-05-04', 'period_end' => '2026-05-04', 'period_type' => 'custom']);
    app(CalculatePayrollRun::class)->handle($run, [$employee->id]);
    $row = $run->employees()->firstOrFail();
    expect((float) $row->basic_salary)->toBe(333.33)
        ->and((float) $row->penalty_amount)->toBe(0.0)
        ->and($employee->attendanceDailySummaries()->firstOrFail()->late_minutes)->toBe(15);
});

it('calculates batch payroll from merged attendance summaries and locks recalculation after approval', function () {
    $this->seed(PayrollTaxRuleSeeder::class);
    $this->seed(OvertimeRuleSeeder::class);

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
        ->and($payrollRun->overtimeEntries()->count())->toBe(0)
        ->and((float) $payrollEmployee->overtime_amount)->toBe(0.0)
        ->and((float) $payrollEmployee->income_tax)->toBeGreaterThan(0)
        ->and((float) $payrollEmployee->net_pay)->toBeGreaterThan(0);

    app(PostPayrollRun::class)->handle(tap($payrollRun, fn ($run) => $run->update(['attendance_reviewed_at' => now()])));

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

    AttendancePeriodSummary::create([
        'employee_id' => $employee->id,
        'period_start' => '2026-05-01',
        'period_end' => '2026-05-31',
        'work_days' => 26,
        'actual_days' => 26,
        'work_time_hours' => 208,
    ]);

    app(CalculatePayrollRun::class)->handle($payrollRun, [$employee->id]);

    $payrollEmployee = $payrollRun->employees()->firstOrFail();

    expect((float) $payrollEmployee->transport_allowance)->toBe(150.0)
        ->and((float) $payrollEmployee->gross_earning)->toBe((float) $payrollEmployee->basic_salary + 150.0);
});

it('holds missing attendance for review without deleting monthly salary entitlement', function () {
    $this->seed(PayrollTaxRuleSeeder::class);

    $employee = Employee::create([
        'employee_id' => 'EMP-NO-ATTENDANCE',
        'attendance_device_id' => '704',
        'first_name' => 'No',
        'last_name' => 'Attendance',
        'phone' => '0911777004',
        'hire_date' => '2026-01-01',
        'status' => 'active',
        'basic_salary' => 12000,
        'overtime_multiplier' => 1,
    ]);

    $payrollRun = PayrollRun::create([
        'name' => 'No attendance payroll',
        'period_start' => '2026-05-01',
        'period_end' => '2026-05-31',
    ]);

    app(CalculatePayrollRun::class)->handle($payrollRun, [$employee->id]);

    $payrollEmployee = $payrollRun->employees()->firstOrFail();

    expect((float) $payrollEmployee->basic_salary)->toBe(12000.0)
        ->and($payrollEmployee->calculation_snapshot['attendance_issues'])->not->toBeEmpty();
});

it('prepares a monthly payroll draft from filters and exports bank advice', function () {
    $this->seed(PayrollTaxRuleSeeder::class);

    $bank = Bank::create([
        'name' => 'CBE Payroll',
        'code' => 'CBE-PAY',
        'account_number' => '100000',
        'account_holder_name' => 'PrintOS',
        'bank_name' => 'Commercial Bank of Ethiopia',
        'current_balance' => 100000,
        'status' => 'active',
    ]);
    $included = Employee::create([
        'employee_id' => 'EMP-MONTHLY-1',
        'attendance_device_id' => '705',
        'first_name' => 'Monthly',
        'last_name' => 'Included',
        'phone' => '0911777005',
        'hire_date' => '2026-05-01',
        'status' => 'active',
        'department' => 'Finance',
        'employment_type' => 'permanent',
        'basic_salary' => 12000,
        'bank_name' => 'Commercial Bank of Ethiopia',
        'account_number' => '111111',
    ]);
    $excluded = Employee::create([
        'employee_id' => 'EMP-MONTHLY-2',
        'attendance_device_id' => '706',
        'first_name' => 'Monthly',
        'last_name' => 'Other',
        'phone' => '0911777006',
        'hire_date' => '2026-05-01',
        'status' => 'active',
        'department' => 'Production',
        'employment_type' => 'permanent',
        'basic_salary' => 12000,
    ]);

    AttendancePeriodSummary::create([
        'employee_id' => $included->id,
        'period_start' => '2026-05-01',
        'period_end' => '2026-05-31',
        'work_days' => 26,
        'actual_days' => 26,
        'work_time_hours' => 208,
    ]);
    AttendancePeriodSummary::create([
        'employee_id' => $excluded->id,
        'period_start' => '2026-05-01',
        'period_end' => '2026-05-31',
        'work_days' => 26,
        'actual_days' => 26,
        'work_time_hours' => 208,
    ]);

    $payrollRun = PayrollRun::create([
        'name' => 'May monthly payroll',
        'period_type' => 'monthly',
        'payroll_month' => '2026-05-01',
        'department' => 'Finance',
        'period_start' => '2026-05-01',
        'period_end' => '2026-05-31',
    ]);

    app(PrepareMonthlyPayrollRun::class)->handle($payrollRun);
    $this->actingAs(User::factory()->create(['role' => UserRole::Finance]));
    $payrollRun->update(['attendance_reviewed_at' => now()]);
    app(PostPayrollRun::class)->handle($payrollRun);
    app(ManagePayrollPaymentBatch::class)->prepare($payrollRun->refresh(), $bank);
    $csv = app(ExportPayrollBankAdvice::class)->csv($payrollRun->refresh(), $bank);

    expect($payrollRun->employees()->count())->toBe(1)
        ->and($payrollRun->employees()->firstOrFail()->employee_id)->toBe($included->id)
        ->and($payrollRun->prepared_at)->not->toBeNull()
        ->and($csv)->toContain('Beneficiary Name')
        ->and($csv)->toContain('Monthly Included')
        ->and($csv)->not->toContain('Monthly Other');
});

it('renders payroll runs as an interactive workspace with setup action', function () {
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    $employee = Employee::create([
        'employee_id' => 'EMP-WORKSPACE-1',
        'attendance_device_id' => '708',
        'first_name' => 'Workspace',
        'last_name' => 'Tester',
        'phone' => '0911777008',
        'hire_date' => '2026-05-01',
        'status' => 'active',
        'basic_salary' => 12000,
    ]);
    $payrollRun = PayrollRun::create([
        'name' => 'Workspace payroll',
        'period_type' => 'monthly',
        'payroll_month' => '2026-05-01',
        'period_start' => '2026-05-01',
        'period_end' => '2026-05-31',
        'pay_date' => '2026-05-31',
    ]);

    PayrollRunEmployee::withoutEvents(fn () => $payrollRun->employees()->create([
        'employee_id' => $employee->id,
        'basic_salary' => 12000,
        'gross_earning' => 12000,
        'income_tax' => 1000,
        'total_deduction' => 1000,
        'net_pay' => 11000,
        'calculation_snapshot' => [
            'base_days' => 26,
            'paid_days' => 26,
            'attended_days' => 26,
        ],
    ]));

    Livewire::test(EditPayrollRun::class, ['record' => $payrollRun->id])
        ->assertDontSeeHtml('fi-breadcrumbs')
        ->assertSee('All Employees')
        ->assertSee('Payroll cost')
        ->assertSee('Employees net pay')
        ->assertSee('Workspace Tester')
        ->assertSee('May 2026')
        ->assertSee('Paid days')
        ->assertSee('Gross pay')
        ->assertSee('Net pay')
        ->assertSee('Add employee')
        ->assertActionVisible('setupPayroll')
        ->assertActionVisible('addEmployee')
        ->assertActionEnabled('addEmployee')
        ->assertSeeHtml('md:hidden')
        ->mountAction('setupPayroll')
        ->assertActionDataSet([
            'period_type' => 'monthly',
            'payroll_month' => '2026-05-01',
        ]);
});

it('creates a payroll draft from the list page setup modal and opens the workspace', function () {
    $this->seed(PayrollTaxRuleSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    expect(file_get_contents(base_path('app/Filament/Resources/PayrollRuns/Pages/ListPayrollRuns.php')))
        ->not->toContain('->slideOver()');

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    $employee = Employee::create([
        'employee_id' => 'EMP-MODAL-PAYROLL',
        'attendance_device_id' => '709',
        'first_name' => 'Modal',
        'last_name' => 'Payroll',
        'phone' => '0911777009',
        'hire_date' => '2026-05-01',
        'status' => 'active',
        'basic_salary' => 12000,
    ]);

    AttendancePeriodSummary::create([
        'employee_id' => $employee->id,
        'period_start' => '2026-05-01',
        'period_end' => '2026-05-31',
        'work_days' => 26,
        'actual_days' => 26,
        'work_time_hours' => 208,
    ]);

    Livewire::test(ListPayrollRuns::class)
        ->mountAction('createPayroll')
        ->setActionData([
            'period_type' => 'custom',
            'period_range' => '2026-05-01 - 2026-05-31',
            'pay_date' => '2026-05-31',
        ])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    $payrollRun = PayrollRun::query()->firstOrFail();

    expect($payrollRun->name)->toBe('Payroll: May 1 - May 31, 2026')
        ->and($payrollRun->employees()->count())->toBe(1);
});

it('creates payroll drafts for multiple selected employment types', function () {
    $this->seed(PayrollTaxRuleSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    foreach ([
        ['EMP-PERM', 'Permanent', 'permanent'],
        ['EMP-CONTRACT', 'Contract', 'contract'],
        ['EMP-INTERN', 'Intern', 'intern'],
    ] as [$employeeId, $name, $employmentType]) {
        Employee::create([
            'employee_id' => $employeeId,
            'attendance_device_id' => $employeeId,
            'first_name' => $name,
            'last_name' => 'Worker',
            'phone' => fake()->unique()->numerify('091#######'),
            'hire_date' => '2026-05-01',
            'status' => 'active',
            'employment_type' => $employmentType,
            'basic_salary' => 12000,
        ]);
    }

    Livewire::test(ListPayrollRuns::class)
        ->mountAction('createPayroll')
        ->setActionData([
            'period_type' => 'custom',
            'period_range' => '2026-05-01 - 2026-05-31',
            'pay_date' => '2026-05-31',
            'employment_type' => ['permanent', 'contract'],
        ])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    $payrollRun = PayrollRun::query()->firstOrFail();

    expect(PayrollRun::decodeEmploymentTypes($payrollRun->employment_type))->toBe(['permanent', 'contract'])
        ->and($payrollRun->employees()->count())->toBe(2);
});

it('updates payroll employee detail earnings and deductions from the workspace drawer', function () {
    $this->seed(PayrollTaxRuleSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    $employee = Employee::create([
        'employee_id' => 'EMP-DETAIL',
        'attendance_device_id' => 'EMP-DETAIL',
        'first_name' => 'Detail',
        'last_name' => 'Worker',
        'phone' => '0911888999',
        'hire_date' => '2026-05-01',
        'status' => 'active',
        'basic_salary' => 10000,
    ]);

    $payrollRun = PayrollRun::create([
        'name' => 'Detail payroll',
        'period_start' => '2026-05-01',
        'period_end' => '2026-05-31',
    ]);

    $row = PayrollRunEmployee::withoutEvents(fn () => $payrollRun->employees()->create([
        'employee_id' => $employee->id,
        'basic_salary' => 10000,
        'gross_earning' => 10000,
        'taxable_amount' => 10000,
        'income_tax' => 0,
        'total_deduction' => 0,
        'net_pay' => 10000,
        'calculation_snapshot' => [
            'base_days' => 26,
            'paid_days' => 26,
            'full_basic_salary' => 10000,
            'pension_enabled' => false,
            'union_enabled' => false,
            'tax' => ['rate' => 0, 'deduction' => 0],
        ],
    ]));

    Livewire::test(EditPayrollRun::class, ['record' => $payrollRun->id])
        ->call('showPayrollDetails', $row->id)
        ->set('newEarningType', 'bonus')
        ->call('addPayrollDetailLineItem', 'earning')
        ->set('detailForm.bonus', '250')
        ->call('updatePayrollDetailLine')
        ->call('removePayrollDetailLineItem', 'earning', 'bonus')
        ->call('addPayrollDetailLineItem', 'earning', 'overtime_amount')
        ->set('detailForm.overtime_amount', '125.50')
        ->call('updatePayrollDetailLine')
        ->set('newEarningType', 'commission')
        ->call('addPayrollDetailLineItem', 'earning')
        ->set('detailForm.manual_earnings.0.amount', '500')
        ->call('updatePayrollDetailLine')
        ->set('newDeductionType', 'advance')
        ->call('addPayrollDetailLineItem', 'deduction')
        ->set('detailForm.manual_deductions.0.amount', '100')
        ->call('updatePayrollDetailLine');

    $row->refresh();

    expect((float) $row->overtime_amount)->toBe(0.0)
        ->and((float) $row->gross_earning)->toBe(10500.0)
        ->and((float) $row->net_pay)->toBe(round((float) $row->gross_earning - (float) $row->total_deduction, 2))
        ->and($row->lineItems()->where('code', 'commission')->exists())->toBeTrue()
        ->and($row->lineItems()->where('code', 'overtime_amount')->exists())->toBeFalse()
        ->and($row->lineItems()->where('code', 'advance')->exists())->toBeTrue()
        ->and($row->lineItems()->where('code', 'bonus')->exists())->toBeFalse();
});

it('keeps payroll nets non negative and retains zero net employees in the workspace', function () {
    $this->seed(PayrollTaxRuleSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    $employee = Employee::create([
        'employee_id' => 'EMP-ZERO-NET',
        'attendance_device_id' => 'EMP-ZERO-NET',
        'first_name' => 'Zero',
        'last_name' => 'Net',
        'phone' => '0911888000',
        'hire_date' => '2026-05-01',
        'status' => 'active',
        'basic_salary' => 1000,
    ]);

    $payrollRun = PayrollRun::create([
        'name' => 'Zero net payroll',
        'period_start' => '2026-05-01',
        'period_end' => '2026-05-31',
    ]);

    $row = PayrollRunEmployee::withoutEvents(fn () => $payrollRun->employees()->create([
        'employee_id' => $employee->id,
        'basic_salary' => 100,
        'gross_earning' => 100,
        'income_tax' => 0,
        'loan' => 500,
        'total_deduction' => 500,
        'net_pay' => -400,
        'calculation_snapshot' => [
            'base_days' => 26,
            'paid_days' => 1,
            'full_basic_salary' => 1000,
            'pension_enabled' => false,
            'union_enabled' => false,
            'tax' => ['rate' => 0, 'deduction' => 0],
        ],
    ]));

    $component = Livewire::test(EditPayrollRun::class, ['record' => $payrollRun->id])
        ->call('showPayrollDetails', $row->id)
        ->call('updatePayrollDetailLine');

    expect((float) $row->refresh()->net_pay)->toBe(0.0)
        ->and($component->instance()->payrollRows())->toHaveCount(1);
});

it('removes and adds payroll employees back from the workspace roster', function () {
    $this->seed(PayrollTaxRuleSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    $first = Employee::create([
        'employee_id' => 'EMP-ROSTER-1',
        'attendance_device_id' => 'EMP-ROSTER-1',
        'first_name' => 'Roster',
        'last_name' => 'One',
        'phone' => '0911888001',
        'hire_date' => '2026-05-01',
        'status' => 'active',
        'basic_salary' => 10000,
    ]);
    $second = Employee::create([
        'employee_id' => 'EMP-ROSTER-2',
        'attendance_device_id' => 'EMP-ROSTER-2',
        'first_name' => 'Roster',
        'last_name' => 'Two',
        'phone' => '0911888002',
        'hire_date' => '2026-05-01',
        'status' => 'active',
        'basic_salary' => 10000,
    ]);

    AttendancePeriodSummary::create([
        'employee_id' => $first->id,
        'period_start' => '2026-05-01',
        'period_end' => '2026-05-31',
        'work_days' => 26,
        'actual_days' => 26,
        'work_time_hours' => 208,
    ]);
    AttendancePeriodSummary::create([
        'employee_id' => $second->id,
        'period_start' => '2026-05-01',
        'period_end' => '2026-05-31',
        'work_days' => 26,
        'actual_days' => 26,
        'work_time_hours' => 208,
    ]);

    $payrollRun = PayrollRun::create([
        'name' => 'Roster payroll',
        'period_start' => '2026-05-01',
        'period_end' => '2026-05-31',
    ]);

    app(CalculatePayrollRun::class)->handle($payrollRun, [$first->id, $second->id]);
    $removedRow = $payrollRun->refresh()->employees()->where('employee_id', $second->id)->firstOrFail();

    $component = Livewire::test(EditPayrollRun::class, ['record' => $payrollRun->id])
        ->call('removePayrollEmployee', $removedRow->id);

    expect($payrollRun->refresh()->employees()->pluck('employee_id')->all())->toBe([$first->id])
        ->and($component->instance()->addableEmployeeOptions())->toHaveKey($second->id)
        ->and($component->instance()->addableEmployeeOptions(''))->toBe([])
        ->and($component->instance()->addableEmployeeOptions('Two'))->toHaveKey($second->id);

    $component
        ->mountAction('addEmployee')
        ->setActionData(['employee_ids' => [$second->id]])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect($payrollRun->refresh()->employees()->pluck('employee_id')->sort()->values()->all())->toBe([$first->id, $second->id])
        ->and($component->instance()->summary()['employee_count'])->toBe(2);
});

it('retains explicitly selected employees with attendance review holds', function () {
    $this->seed(PayrollTaxRuleSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    $employees = collect(['One', 'Two'])->map(fn (string $name, int $index): Employee => Employee::create([
        'employee_id' => 'EMP-ZERO-ROSTER-'.$index,
        'attendance_device_id' => 'EMP-ZERO-ROSTER-'.$index,
        'first_name' => 'Zero Roster',
        'last_name' => $name,
        'phone' => fake()->unique()->numerify('091#######'),
        'hire_date' => '2026-05-01',
        'status' => 'active',
        'basic_salary' => 10000,
    ]));

    $payrollRun = PayrollRun::create([
        'name' => 'Zero roster payroll',
        'period_start' => '2026-06-08',
        'period_end' => '2026-07-07',
    ]);

    $component = Livewire::test(EditPayrollRun::class, ['record' => $payrollRun->id])
        ->mountAction('addEmployee')
        ->setActionData(['employee_ids' => $employees->pluck('id')->all()])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect($payrollRun->refresh()->employees()->count())->toBe(2)
        ->and((float) $payrollRun->employees()->sum('net_pay'))->toBeGreaterThan(0)
        ->and($component->instance()->payrollRows())->toHaveCount(2)
        ->and($component->instance()->summary()['employee_count'])->toBe(2)
        ->and($component->instance()->addableEmployeeOptions())->not->toHaveKeys($employees->pluck('id')->all());
});

it('counts only payable employees on payroll workspace and list table', function () {
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    $payrollRun = PayrollRun::create([
        'name' => 'Payable count payroll',
        'period_start' => '2026-05-01',
        'period_end' => '2026-05-02',
    ]);

    foreach ([['One', 1000], ['Two', 2000], ['Zero', 0]] as $index => [$name, $netPay]) {
        $employee = Employee::create([
            'employee_id' => 'EMP-PAYABLE-'.$index,
            'attendance_device_id' => 'EMP-PAYABLE-'.$index,
            'first_name' => 'Payable',
            'last_name' => $name,
            'phone' => fake()->unique()->numerify('091#######'),
            'hire_date' => '2026-05-01',
            'status' => 'active',
            'basic_salary' => 10000,
        ]);

        PayrollRunEmployee::withoutEvents(fn () => $payrollRun->employees()->create([
            'employee_id' => $employee->id,
            'basic_salary' => $netPay,
            'gross_earning' => $netPay,
            'net_pay' => $netPay,
            'calculation_snapshot' => ['base_days' => 2, 'paid_days' => $netPay > 0 ? 2 : 0],
        ]));
    }

    $workspace = Livewire::test(EditPayrollRun::class, ['record' => $payrollRun->id]);

    expect($workspace->instance()->summary()['employee_count'])->toBe(3)
        ->and($workspace->instance()->payrollRows())->toHaveCount(3);

    Livewire::test(ListPayrollRuns::class)
        ->assertCanSeeTableRecords([$payrollRun])
        ->assertTableColumnStateSet('payable_employees_count', '2', $payrollRun)
        ->assertTableColumnStateSet('payable_employees_net_pay', Money::format(3000, 2), $payrollRun);
});

it('prevents penalty overrides from the detail drawer', function () {
    $this->seed(PayrollTaxRuleSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    $employee = Employee::create([
        'employee_id' => 'EMP-PENALTY',
        'attendance_device_id' => 'EMP-PENALTY',
        'first_name' => 'Penalty',
        'last_name' => 'Worker',
        'phone' => '0911888003',
        'hire_date' => '2026-05-01',
        'status' => 'active',
        'basic_salary' => 10000,
    ]);

    $payrollRun = PayrollRun::create([
        'name' => 'Penalty payroll',
        'period_start' => '2026-05-01',
        'period_end' => '2026-05-31',
    ]);

    $row = PayrollRunEmployee::withoutEvents(fn () => $payrollRun->employees()->create([
        'employee_id' => $employee->id,
        'basic_salary' => 10000,
        'gross_earning' => 10000,
        'income_tax' => 0,
        'penalty_hours' => 1,
        'penalty_amount' => 41.67,
        'total_deduction' => 41.67,
        'net_pay' => 9958.33,
        'calculation_snapshot' => [
            'base_days' => 26,
            'paid_days' => 26,
            'full_basic_salary' => 10000,
            'pension_enabled' => false,
            'union_enabled' => false,
            'tax' => ['rate' => 0, 'deduction' => 0],
        ],
    ]));

    Livewire::test(EditPayrollRun::class, ['record' => $payrollRun->id])
        ->call('showPayrollDetails', $row->id)
        ->call('editPayrollDetailLine', 'deduction', 'penalty_hours')
        ->set('detailForm.penalty_hours', '2')
        ->call('updatePayrollDetailLine')
        ->call('removePayrollDetailLineItem', 'deduction', 'penalty_hours');

    expect((float) $row->refresh()->penalty_hours)->toBe(0.0)
        ->and((float) $row->penalty_amount)->toBe(0.0)
        ->and($row->lineItems()->where('code', 'penalty_hours')->exists())->toBeFalse();
});

it('colors the payroll title status badge by workflow status', function () {
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    $approved = PayrollRun::create([
        'name' => 'Approved payroll',
        'status' => 'approved',
        'period_start' => '2026-05-01',
        'period_end' => '2026-05-31',
    ]);
    $paid = PayrollRun::create([
        'name' => 'Paid payroll',
        'status' => 'paid',
        'period_start' => '2026-05-01',
        'period_end' => '2026-05-31',
    ]);

    expect(Livewire::test(EditPayrollRun::class, ['record' => $approved->id])->instance()->getTitle()->toHtml())->toContain('bg-warning-50')
        ->and(Livewire::test(EditPayrollRun::class, ['record' => $paid->id])->instance()->getTitle()->toHtml())->toContain('bg-success-50');
});

it('generates exclusive overtime candidates independently and pays only approved entries', function (): void {
    $this->seed(PayrollTaxRuleSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('finance'));
    $this->actingAs(User::factory()->create(['role' => UserRole::Finance]));
    $employee = Employee::factory()->create(['basic_salary' => 24000, 'pension_enabled' => false]);
    $night = Shift::factory()->create(['start_time' => '22:00:00', 'end_time' => '06:00:00', 'break_start' => null, 'break_end' => null, 'break_minutes' => 0]);
    EmployeeShiftOverride::create(['employee_id' => $employee->id, 'date' => '2026-05-07', 'shift_id' => $night->id, 'reason' => 'Night shift']);
    Holiday::create(['name' => 'Holiday', 'date' => '2026-05-08', 'counts_as_holiday_overtime' => true, 'is_paid' => true]);
    foreach ([['2026-05-04', '08:00:00', '18:00:00'], ['2026-05-05', '07:00:00', '17:00:00'], ['2026-05-06', '08:00:00', '19:00:00'], ['2026-05-07', '22:00:00', '06:00:00'], ['2026-05-08', '08:00:00', '10:00:00'], ['2026-05-10', '08:00:00', '10:00:00']] as [$date, $in, $out]) {
        AttendanceSegment::create(['employee_id' => $employee->id, 'date' => $date, 'fp_no' => $employee->attendance_device_id, 'clock_in' => $in, 'clock_out' => $out, 'status' => 'Present']);
    }
    seedPayrollPeriodPunches($employee, '2026-05-09', '2026-05-09');
    app(GeneratePayrollOvertimeEntries::class)->generate($employee, '2026-05-04', '2026-05-10');
    $entries = PayrollOvertimeEntry::orderBy('date')->get();
    expect($entries)->toHaveCount(6)->and($entries->where('category', 'night'))->toHaveCount(0);
    $run = PayrollRun::factory()->create(['period_start' => '2026-05-04', 'period_end' => '2026-05-10']);
    app(CalculatePayrollRun::class)->handle($run, [$employee->id]);
    expect((float) $run->employees()->firstOrFail()->overtime_amount)->toBe(0.0);
    $component = Livewire::test(EditPayrollRun::class, ['record' => $run->id])
        ->assertActionVisible('reviewOvertime')->call('approveOvertimeEntry', $entries[0]->id)->call('rejectOvertimeEntry', $entries[2]->id);
    expect((float) $run->employees()->firstOrFail()->overtime_amount)->toBe(150.0)
        ->and($component->instance()->pendingOvertimeApprovalCount())->toBe(4);
    PayrollOvertimeEntry::where('status', 'pending')->update(['status' => 'rejected']);
    app(CalculatePayrollRun::class)->handle($run, [$employee->id]);
    $run->update(['attendance_reviewed_at' => now()]);
    $journal = app(PostPayrollRun::class)->handle($run);
    expect((float) $journal->journalItems()->whereBelongsTo(Account::where('code', '5110')->firstOrFail())->firstOrFail()->debit)->toBe(150.0);
});

it('replaces standalone overtime rules with shared versioned pay policies', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));
    $this->actingAs(User::factory()->create(['role' => UserRole::Finance]));
    expect(OvertimeRuleResource::canAccess())->toBeFalse()->and(OvertimeRuleResource::shouldRegisterNavigation())->toBeFalse();
    $this->seed(HrWorkSetupSeeder::class);
    Livewire::test(ManagePayPolicies::class)
        ->assertRedirect(Settings::getUrl(panel: 'finance', parameters: ['section' => 'payroll']));
    Livewire::test(Settings::class)
        ->assertSee('Current pay policy')
        ->assertSee('Standard monthly pay')
        ->assertFormSet(['pay_rules.monthly_day_divisor' => 30]);
});

it('exports the policy payroll formula with separate employee and employer pension', function (): void {
    $rule = PayrollTaxRule::create(['name' => 'Sample tax', 'jurisdiction' => 'ET', 'effective_from' => '2010-01-01', 'is_active' => true]);
    $rule->brackets()->create(['min_income' => 0, 'max_income' => null, 'rate' => 35, 'deduction' => 2050]);
    $employee = Employee::factory()->create(['basic_salary' => 15432, 'transport_allowance' => 0, 'pension_enabled' => true]);
    $loan = $employee->loans()->create(['legacy_disbursement_confirmed' => true, 'loan_date' => '2026-05-03', 'return_date' => '2026-05-31', 'amount' => 3750, 'reason' => 'Loan', 'status' => 'active']);
    AttendanceSegment::create(['employee_id' => $employee->id, 'date' => '2026-05-04', 'fp_no' => $employee->attendance_device_id, 'clock_in' => '08:00:00', 'clock_out' => '18:01:00', 'status' => 'Present']);
    app(GeneratePayrollOvertimeEntries::class)->generate($employee, '2026-05-04', '2026-05-04');
    PayrollOvertimeEntry::firstOrFail()->update(['status' => 'approved']);
    $run = PayrollRun::factory()->create();
    app(CalculatePayrollRun::class)->handle($run, [$employee->id]);
    $row = $run->employees()->firstOrFail();
    expect((float) $row->basic_salary)->toBe(15432.0)
        ->and((float) $row->pay_per_hour)->toBe(64.3)
        ->and((float) $row->overtime_amount)->toBe(98.06)
        ->and((float) $row->employer_pension_contribution)->toBe(1697.52)
        ->and((float) $row->pension_contribution)->toBe(1080.24)
        ->and((float) $row->gross_earning)->toBe(15530.06)
        ->and((float) $row->net_pay)->toBe(7159.98)
        ->and($row->calculation_snapshot['loan_ids'])->toContain($loan->id)
        ->and(app(ExportPayrollRegisterCsv::class)->csv($run))->toContain('Employer Pension', 'Employee Pension', '7159.98');
});

it('does not turn legacy aggregate lateness into a second salary deduction', function (): void {
    $this->seed(PayrollTaxRuleSeeder::class);
    $employee = Employee::factory()->create(['basic_salary' => 15432.50]);
    AttendancePeriodSummary::create(['employee_id' => $employee->id, 'period_start' => '2026-05-01', 'period_end' => '2026-05-31', 'work_days' => 26, 'actual_days' => 26, 'late_minutes' => 720]);
    $run = PayrollRun::factory()->create();
    app(CalculatePayrollRun::class)->handle($run, [$employee->id]);
    $row = $run->employees()->firstOrFail();
    expect((float) $row->basic_salary)->toBe(15432.50)->and((float) $row->penalty_amount)->toBe(0.0);
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
        'legacy_disbursement_confirmed' => true,
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

    AttendancePeriodSummary::create([
        'employee_id' => $employee->id,
        'period_start' => '2026-05-01',
        'period_end' => '2026-05-31',
        'work_days' => 26,
        'actual_days' => 26,
        'work_time_hours' => 208,
    ]);

    app(CalculatePayrollRun::class)->handle($payrollRun, [$employee->id]);

    $payrollEmployee = $payrollRun->employees()->firstOrFail();
    $firstInstallment = $loan->installments()->orderBy('due_date')->firstOrFail();

    expect($loan->installments()->count())->toBe(3)
        ->and((float) $payrollEmployee->loan)->toBe(400.0)
        ->and($payrollEmployee->calculation_snapshot['loan_installment_ids'])->toBe([$firstInstallment->id]);

    app(PostPayrollRun::class)->handle(tap($payrollRun, fn ($run) => $run->update(['attendance_reviewed_at' => now()])));

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

    $loan = $employee->loans()->create(['loan_date' => '2026-05-01', 'return_date' => '2026-05-31', 'amount' => 20, 'status' => 'active', 'legacy_disbursement_confirmed' => true]);
    $payrollEmployee->forceFill(['calculation_snapshot' => ['loan_installment_ids' => $loan->installments()->pluck('id')->all()]])->saveQuietly();
    $entry = app(PostPayrollRun::class)->handle(tap($payrollRun, fn ($run) => $run->update(['attendance_reviewed_at' => now()])));

    expect((float) $entry->total_debit)->toBe(1420.0)
        ->and((float) $entry->total_credit)->toBe(1420.0)
        ->and((float) $entry->journalItems()->whereBelongsTo(Account::where('code', '5100')->firstOrFail())->firstOrFail()->debit)->toBe(1110.0)
        ->and((float) $entry->journalItems()->whereBelongsTo(Account::where('code', '5110')->firstOrFail())->firstOrFail()->debit)->toBe(200.0)
        ->and((float) $entry->journalItems()->whereBelongsTo(Account::where('code', '5120')->firstOrFail())->firstOrFail()->debit)->toBe(110.0)
        ->and((float) $entry->journalItems()->whereBelongsTo(Account::where('code', '2160')->firstOrFail())->firstOrFail()->credit)->toBe(100.0)
        ->and((float) $entry->journalItems()->whereBelongsTo(Account::where('code', '2170')->firstOrFail())->firstOrFail()->credit)->toBe(290.0)
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
        'legacy_disbursement_confirmed' => true,
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
        'legacy_disbursement_confirmed' => true,
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

    AttendancePeriodSummary::create([
        'employee_id' => $employee->id,
        'period_start' => '2026-05-01',
        'period_end' => '2026-05-31',
        'work_days' => 26,
        'actual_days' => 26,
        'work_time_hours' => 208,
    ]);

    app(CalculatePayrollRun::class)->handle($payrollRun, [$employee->id]);

    $payrollEmployee = $payrollRun->employees()->firstOrFail();

    expect((float) $payrollEmployee->loan)->toBe(200.0)
        ->and($loan->refresh()->status)->toBe('partially_paid');

    app(PostPayrollRun::class)->handle(tap($payrollRun, fn ($run) => $run->update(['attendance_reviewed_at' => now()])));

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

    expect((float) $payrollEmployee->calculation_snapshot['unpaid_time_reduction'])->toBe(333.33)
        ->and((int) $payrollEmployee->calculation_snapshot['paid_leave_minutes'])->toBe(0)
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

    AttendancePeriodSummary::create([
        'employee_id' => $employee->id,
        'period_start' => '2026-05-01',
        'period_end' => '2026-05-31',
        'work_days' => 26,
        'actual_days' => 13,
        'work_time_hours' => 104,
    ]);

    app(CalculatePayrollRun::class)->handle($payrollRun, [$employee->id]);

    $payrollEmployee = $payrollRun->employees()->firstOrFail();

    expect((float) $payrollEmployee->basic_salary)->toBe(13419.35)
        ->and((float) $payrollEmployee->calculation_snapshot['full_basic_salary'])->toBe(26000.0)
        ->and($payrollEmployee->calculation_snapshot['salary_portions'])->toHaveCount(16);
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

    expect((float) $payrollEmployee->penalty_amount)->toBe(0.0)
        ->and((float) $payrollEmployee->calculation_snapshot['unpaid_time_reduction'])->toBe(322.58)
        ->and((int) $payrollEmployee->calculation_snapshot['unpaid_minutes'])->toBe(480);
});

it('keeps an empty payroll draft when no active employees exist', function () {
    $this->seed(PayrollTaxRuleSeeder::class);

    $payrollRun = PayrollRun::create([
        'name' => 'Empty payroll',
        'period_start' => '2018-08-25',
        'period_end' => '2018-09-12',
    ]);

    app(CalculatePayrollRun::class)->handle($payrollRun);

    expect($payrollRun->refresh()->employees()->count())->toBe(0);
});

it('posts payroll journals and generates outbound payroll payments', function () {
    $this->seed(PayrollTaxRuleSeeder::class);
    $bank = Bank::create([
        'name' => 'Payroll Bank',
        'code' => 'PAYROLL-BANK',
        'account_number' => '1234567890',
        'account_holder_name' => 'PrintOS',
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

    $journalEntry = app(PostPayrollRun::class)->handle(tap($payrollRun, fn ($run) => $run->update(['attendance_reviewed_at' => now()])));
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

it('retains zero net employees when approving payroll', function () {
    $payableEmployee = Employee::create([
        'employee_id' => 'EMP-POST-PAYABLE',
        'attendance_device_id' => 'EMP-POST-PAYABLE',
        'first_name' => 'Payable',
        'last_name' => 'Worker',
        'phone' => '0911999001',
        'hire_date' => '2026-05-01',
        'status' => 'active',
        'basic_salary' => 10000,
    ]);
    $zeroNetEmployee = Employee::create([
        'employee_id' => 'EMP-POST-ZERO',
        'attendance_device_id' => 'EMP-POST-ZERO',
        'first_name' => 'Zero',
        'last_name' => 'Worker',
        'phone' => '0911999002',
        'hire_date' => '2026-05-01',
        'status' => 'active',
        'basic_salary' => 10000,
    ]);

    $payrollRun = PayrollRun::create([
        'name' => 'Prune zero payroll',
        'selected_employee_ids' => [$payableEmployee->id, $zeroNetEmployee->id],
        'period_start' => '2026-05-01',
        'period_end' => '2026-05-31',
    ]);

    PayrollRunEmployee::withoutEvents(fn () => $payrollRun->employees()->create([
        'employee_id' => $payableEmployee->id,
        'basic_salary' => 1000,
        'gross_earning' => 1000,
        'income_tax' => 100,
        'total_deduction' => 100,
        'net_pay' => 900,
    ]));
    PayrollRunEmployee::withoutEvents(fn () => $payrollRun->employees()->create([
        'employee_id' => $zeroNetEmployee->id,
        'basic_salary' => 0,
        'gross_earning' => 0,
        'loan' => 0,
        'total_deduction' => 0,
        'net_pay' => 0,
    ]));

    $journalEntry = app(PostPayrollRun::class)->handle(tap($payrollRun, fn ($run) => $run->update(['attendance_reviewed_at' => now()])));

    expect($payrollRun->refresh()->employees()->pluck('employee_id')->all())->toBe([$payableEmployee->id, $zeroNetEmployee->id])
        ->and($payrollRun->selected_employee_ids)->toBe([$payableEmployee->id, $zeroNetEmployee->id])
        ->and((float) $journalEntry->total_debit)->toBe(1000.0)
        ->and((float) $journalEntry->total_credit)->toBe(1000.0)
        ->and($journalEntry->journalItems()->whereHas('account', fn ($query) => $query->where('code', '1230'))->exists())->toBeFalse();
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

it('never uses an internal ERP employee ID to match an imported machine account number', function (): void {
    Employee::factory()->create(['employee_id' => '999', 'attendance_device_id' => '22']);
    $path = tempnam(sys_get_temp_dir(), 'account-match-');
    file_put_contents($path, crystalReportCsv('999', 'Unmatched account', '08:00'));
    try {
        $import = app(ImportAttendanceSummaryReport::class)->handle($path, 'normal');
        expect($import->failed_rows)->toBe(1)->and(AttendancePeriodSummary::count())->toBe(0);
    } finally {
        unlink($path);
    }
});

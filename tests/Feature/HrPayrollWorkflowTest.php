<?php

use App\Filament\Resources\OvertimeRules\OvertimeRuleResource;
use App\Filament\Resources\OvertimeRules\Pages\ManageOvertimeRules;
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
use App\Models\Holiday;
use App\Models\JournalEntry;
use App\Models\LeaveType;
use App\Models\OvertimeRule;
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
use App\Services\Hr\GeneratePayrollPayments;
use App\Services\Hr\ImportAttendanceSegmentCsv;
use App\Services\Hr\ImportAttendanceSummaryReport;
use App\Services\Hr\PostPayrollRun;
use App\Services\Hr\PrepareMonthlyPayrollRun;
use App\Services\Hr\RecordManualAttendanceLog;
use App\Services\Hr\RepayEmployeeLoan;
use App\Support\Money;
use App\UserRole;
use Database\Seeders\OvertimeRuleSeeder;
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

it('uses attendance segment worked days and late early minutes in payroll calculation', function () {
    $this->seed(PayrollTaxRuleSeeder::class);

    $employee = Employee::create([
        'employee_id' => 'EMP-SEGMENT-MINUTES',
        'attendance_device_id' => '707',
        'first_name' => 'Segment',
        'last_name' => 'Minutes',
        'phone' => '0911777007',
        'hire_date' => '2026-05-01',
        'status' => 'active',
        'basic_salary' => 10000,
        'overtime_multiplier' => 1,
    ]);

    AttendanceSegment::create([
        'employee_id' => $employee->id,
        'date' => '2026-05-04',
        'fp_no' => '707',
        'worked_minutes' => 470,
        'late_minutes' => 10,
        'early_minutes' => 5,
        'day_fraction' => 1,
        'status' => 'Early',
    ]);

    $payrollRun = PayrollRun::create([
        'name' => 'Segment minute payroll',
        'period_start' => '2026-05-04',
        'period_end' => '2026-05-04',
    ]);

    app(CalculatePayrollRun::class)->handle($payrollRun, [$employee->id]);

    $payrollEmployee = $payrollRun->employees()->firstOrFail();

    expect((float) $payrollEmployee->calculation_snapshot['paid_days'])->toBe(1.0)
        ->and((int) $payrollEmployee->calculation_snapshot['late_minutes'])->toBe(15)
        ->and((float) $payrollEmployee->penalty_hours)->toBe(0.25)
        ->and((float) $payrollEmployee->penalty_amount)->toBe(10.42)
        ->and((float) $payrollEmployee->basic_salary)->toBe(10000.0);
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
        ->and($payrollRun->overtimeEntries()->where('status', PayrollOvertimeEntry::STATUS_PENDING)->count())->toBeGreaterThan(0)
        ->and((float) $payrollEmployee->overtime_amount)->toBe(0.0)
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

it('does not pay a full month when an employee has no attendance data', function () {
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

    expect((float) $payrollEmployee->basic_salary)->toBe(0.0)
        ->and((float) $payrollEmployee->gross_earning)->toBe(0.0)
        ->and((float) $payrollEmployee->net_pay)->toBe(0.0)
        ->and($payrollEmployee->calculation_snapshot['has_attendance_data'])->toBeFalse();
});

it('prepares a monthly payroll draft from filters and exports bank advice', function () {
    $this->seed(PayrollTaxRuleSeeder::class);

    $bank = Bank::create([
        'name' => 'CBE Payroll',
        'code' => 'CBE-PAY',
        'account_number' => '100000',
        'account_holder_name' => 'Packledge',
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

    expect((float) $row->overtime_amount)->toBe(125.5)
        ->and((float) $row->gross_earning)->toBe(10625.5)
        ->and((float) $row->total_deduction)->toBe(100.0)
        ->and((float) $row->net_pay)->toBe(10525.5)
        ->and($row->lineItems()->where('code', 'commission')->exists())->toBeTrue()
        ->and($row->lineItems()->where('code', 'overtime_amount')->exists())->toBeTrue()
        ->and($row->lineItems()->where('code', 'advance')->exists())->toBeTrue()
        ->and($row->lineItems()->where('code', 'bonus')->exists())->toBeFalse();
});

it('keeps payroll nets non negative and hides zero net employees from the workspace', function () {
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
        ->and($component->instance()->payrollRows())->toHaveCount(0);
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

it('shows explicitly selected payroll employees even when their calculated net is zero', function () {
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
        ->and((float) $payrollRun->employees()->sum('net_pay'))->toBe(0.0)
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

    expect($workspace->instance()->summary()['employee_count'])->toBe(2)
        ->and($workspace->instance()->payrollRows())->toHaveCount(2);

    Livewire::test(ListPayrollRuns::class)
        ->assertCanSeeTableRecords([$payrollRun])
        ->assertTableColumnStateSet('payable_employees_count', '2', $payrollRun)
        ->assertTableColumnStateSet('payable_employees_net_pay', Money::format(3000, 2), $payrollRun);
});

it('edits and removes payroll penalty from the detail drawer', function () {
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

it('generates overtime candidates and pays only approved entries', function () {
    $this->seed(PayrollTaxRuleSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    foreach ([
        ['Normal', 'normal_test', OvertimeRule::BASIS_ATTENDANCE_OVERTIME, [OvertimeRule::DAY_REGULAR], 1.5, null, null, 10],
        ['Before shift', 'before_test', OvertimeRule::BASIS_BEFORE_SHIFT, [OvertimeRule::DAY_REGULAR], 1.1, null, null, 20],
        ['After 5:30 PM', 'after_530_test', OvertimeRule::BASIS_AFTER_CLOCK_TIME, [OvertimeRule::DAY_REGULAR], 1.25, '17:30:00', null, 30],
        ['Night', 'night_test', OvertimeRule::BASIS_TIME_WINDOW, [OvertimeRule::DAY_REGULAR], 2.0, '22:00:00', '06:00:00', 40],
        ['Holiday', 'holiday_test', OvertimeRule::BASIS_WORKED_DAY, [OvertimeRule::DAY_HOLIDAY], 2.5, null, null, 5],
        ['Day off', 'dayoff_test', OvertimeRule::BASIS_WORKED_DAY, [OvertimeRule::DAY_WEEKEND_DAYOFF], 2.0, null, null, 6],
    ] as [$name, $code, $minutesBasis, $appliesOnDays, $multiplier, $windowStart, $windowEnd, $priority]) {
        OvertimeRule::create([
            'name' => $name,
            'code' => $code,
            'minutes_basis' => $minutesBasis,
            'applies_on_days' => $appliesOnDays,
            'multiplier' => $multiplier,
            'hourly_rate' => 100,
            'minimum_minutes' => 0,
            'rounding_increment_minutes' => 1,
            'window_start_time' => $windowStart,
            'window_end_time' => $windowEnd,
            'priority' => $priority,
            'is_active' => true,
        ]);
    }

    $employee = Employee::create([
        'employee_id' => 'EMP-OT-RULES',
        'attendance_device_id' => 'EMP-OT-RULES',
        'first_name' => 'Overtime',
        'last_name' => 'Rules',
        'phone' => '0911888555',
        'hire_date' => '2026-05-01',
        'status' => 'active',
        'basic_salary' => 24000,
        'pension_enabled' => false,
    ]);
    $dayShift = Shift::create([
        'name' => 'Day overtime shift',
        'start_time' => '08:00:00',
        'end_time' => '16:00:00',
        'expected_minutes' => 480,
        'overtime_eligible' => true,
    ]);
    $nightShift = Shift::create([
        'name' => 'Night overtime shift',
        'start_time' => '22:00:00',
        'end_time' => '06:00:00',
        'expected_minutes' => 480,
        'is_night_shift' => true,
        'overtime_eligible' => true,
    ]);

    Holiday::create([
        'name' => 'Overtime holiday',
        'date' => '2026-05-08',
        'type' => 'public',
        'is_paid' => true,
        'counts_as_holiday_overtime' => true,
    ]);

    foreach ([
        ['2026-05-04', $dayShift->id, '08:00:00', '16:00:00', '08:00:00', '17:00:00', 480, 60, null, null],
        ['2026-05-05', $dayShift->id, '08:00:00', '16:00:00', '07:00:00', '16:00:00', 540, 0, null, null],
        ['2026-05-06', $dayShift->id, '08:00:00', '16:00:00', '08:00:00', '19:00:00', 660, 0, null, null],
        ['2026-05-07', $nightShift->id, '22:00:00', '06:00:00', '22:00:00', '06:00:00', 480, 0, null, null],
        ['2026-05-08', $dayShift->id, '08:00:00', '16:00:00', '08:00:00', '16:00:00', 480, 0, 'Holiday', 'Holiday'],
        ['2026-05-10', $dayShift->id, '08:00:00', '16:00:00', '08:00:00', '16:00:00', 480, 0, 'Dayoff', 'Dayoff'],
    ] as [$date, $shiftId, $scheduledStart, $scheduledEnd, $clockIn, $clockOut, $workedMinutes, $overtimeMinutes, $status, $exception]) {
        AttendanceSegment::create([
            'employee_id' => $employee->id,
            'shift_id' => $shiftId,
            'date' => $date,
            'fp_no' => 'EMP-OT-RULES',
            'scheduled_start' => $scheduledStart,
            'scheduled_end' => $scheduledEnd,
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'worked_minutes' => $workedMinutes,
            'overtime_minutes' => $overtimeMinutes,
            'day_fraction' => 1,
            'status' => $status,
            'exception' => $exception,
        ]);
    }

    $payrollRun = PayrollRun::create([
        'name' => 'Overtime rules payroll',
        'period_start' => '2026-05-04',
        'period_end' => '2026-05-10',
    ]);

    app(CalculatePayrollRun::class)->handle($payrollRun, [$employee->id]);

    $payrollEmployee = $payrollRun->employees()->firstOrFail();
    $entries = $payrollRun->overtimeEntries()->with('overtimeRule')->get();
    $normal = $entries->firstWhere('overtimeRule.code', 'normal_test');
    $afterClockTime = $entries->firstWhere('overtimeRule.code', 'after_530_test');

    expect($entries->map(fn (PayrollOvertimeEntry $entry): string => $entry->overtimeRule->code)->sort()->values()->all())->toBe([
        'after_530_test',
        'before_test',
        'dayoff_test',
        'holiday_test',
        'night_test',
        'normal_test',
    ])
        ->and((float) $entries->firstWhere('overtimeRule.code', 'normal_test')->amount)->toBe(150.0)
        ->and((float) $entries->firstWhere('overtimeRule.code', 'before_test')->amount)->toBe(110.0)
        ->and((float) $afterClockTime->amount)->toBe(187.5)
        ->and((float) $entries->firstWhere('overtimeRule.code', 'night_test')->amount)->toBe(1600.0)
        ->and((float) $entries->firstWhere('overtimeRule.code', 'holiday_test')->amount)->toBe(2000.0)
        ->and((float) $entries->firstWhere('overtimeRule.code', 'dayoff_test')->amount)->toBe(1600.0)
        ->and((float) $payrollEmployee->overtime_amount)->toBe(0.0);

    $component = Livewire::test(EditPayrollRun::class, ['record' => $payrollRun->id])
        ->assertActionVisible('approve')
        ->assertActionVisible('reviewOvertime')
        ->call('approveOvertimeEntry', $normal->id)
        ->call('rejectOvertimeEntry', $afterClockTime->id);

    $payrollEmployee->refresh();

    expect((float) $payrollEmployee->overtime_amount)->toBe(150.0)
        ->and((float) $payrollEmployee->overtime_hours)->toBe(1.0)
        ->and((float) $payrollEmployee->gross_earning)->toBeGreaterThan((float) $payrollEmployee->basic_salary)
        ->and($normal->refresh()->status)->toBe(PayrollOvertimeEntry::STATUS_APPROVED)
        ->and($afterClockTime->refresh()->status)->toBe(PayrollOvertimeEntry::STATUS_REJECTED)
        ->and($component->instance()->approveModalDescription())->toContain('pending overtime candidates')
        ->and($component->instance()->pendingOvertimeApprovalCount())->toBe(4);

    $journalEntry = app(PostPayrollRun::class)->handle($payrollRun->refresh());

    expect((float) $journalEntry->journalItems()->whereBelongsTo(Account::where('code', '5110')->firstOrFail())->firstOrFail()->debit)->toBe(150.0);
});

it('creates overtime rules without exposing internal setup as required workflow', function () {
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    Livewire::test(ManageOvertimeRules::class)
        ->mountAction('create')
        ->setActionData([
            'name' => 'After 5:30 PM overtime',
            'applies_on_days' => [OvertimeRule::DAY_REGULAR],
            'minutes_basis' => OvertimeRule::BASIS_AFTER_CLOCK_TIME,
            'multiplier' => 1.5,
            'hourly_rate' => null,
            'window_start_time' => '17:30:00',
            'is_active' => true,
            'minimum_minutes' => 0,
            'rounding_increment_minutes' => 1,
            'priority' => 100,
        ])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    Livewire::test(ManageOvertimeRules::class)
        ->mountAction('create')
        ->setActionData([
            'name' => 'Night window overtime',
            'applies_on_days' => [OvertimeRule::DAY_REGULAR],
            'minutes_basis' => OvertimeRule::BASIS_TIME_WINDOW,
            'multiplier' => 1.75,
            'hourly_rate' => null,
            'window_range' => [
                'start' => '22:00',
                'end' => '06:00',
            ],
            'is_active' => true,
            'minimum_minutes' => 30,
            'rounding_increment_minutes' => 15,
            'priority' => 100,
        ])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    $rule = OvertimeRule::query()->where('name', 'After 5:30 PM overtime')->firstOrFail();
    $nightRule = OvertimeRule::query()->where('name', 'Night window overtime')->firstOrFail();

    expect($rule->code)->toBe('after_530_pm_overtime')
        ->and($rule->minutes_basis)->toBe(OvertimeRule::BASIS_AFTER_CLOCK_TIME)
        ->and($rule->applies_on_days)->toBe([OvertimeRule::DAY_REGULAR])
        ->and($nightRule->window_start_time)->toBe('22:00:00')
        ->and($nightRule->window_end_time)->toBe('06:00:00')
        ->and(OvertimeRuleResource::minutesBasisExplanation(OvertimeRule::BASIS_TIME_WINDOW))->toContain('inside the selected time range');

    OvertimeRule::create([
        'name' => 'Regular overtime',
        'code' => 'regular_overtime',
        'minutes_basis' => OvertimeRule::BASIS_ATTENDANCE_OVERTIME,
        'applies_on_days' => [OvertimeRule::DAY_REGULAR],
        'multiplier' => 1.5,
        'minimum_minutes' => 0,
        'rounding_increment_minutes' => 1,
        'priority' => 110,
        'is_active' => true,
    ]);

    Livewire::test(ManageOvertimeRules::class)
        ->assertSee('Shift / attendance');
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
    OvertimeRule::create([
        'name' => 'Finance sample overtime',
        'code' => 'finance_sample_overtime',
        'minutes_basis' => OvertimeRule::BASIS_ATTENDANCE_OVERTIME,
        'applies_on_days' => [OvertimeRule::DAY_REGULAR],
        'multiplier' => 1,
        'hourly_rate' => 15432 / 24 / 8,
        'minimum_minutes' => 0,
        'rounding_increment_minutes' => 1,
        'priority' => 10,
        'is_active' => true,
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
    $overtimeEntry = $payrollRun->overtimeEntries()->firstOrFail();

    expect($overtimeEntry->status)->toBe(PayrollOvertimeEntry::STATUS_PENDING)
        ->and((float) $overtimeEntry->hours)->toBe(44.73)
        ->and((float) $overtimeEntry->amount)->toBe(3595.17)
        ->and((float) $payrollEmployee->overtime_amount)->toBe(0.0);

    $overtimeEntry->update(['status' => PayrollOvertimeEntry::STATUS_APPROVED]);
    app(CalculatePayrollRun::class)->handle($payrollRun->refresh(), [$employee->id]);

    $payrollEmployee = $payrollRun->refresh()->employees()->firstOrFail();
    $csv = app(ExportPayrollRegisterCsv::class)->csv($payrollRun->refresh());

    expect((float) $payrollEmployee->basic_salary)->toBe(15432.0)
        ->and((float) $payrollEmployee->pay_per_hour)->toBe(74.1923)
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

it('matches the payroll register penalty formula from the excel sample', function () {
    $rule = PayrollTaxRule::create([
        'name' => 'Penalty sample PAYE',
        'jurisdiction' => 'ET',
        'effective_from' => '2010-01-01',
        'is_active' => true,
    ]);
    $rule->brackets()->create([
        'min_income' => 0,
        'max_income' => null,
        'rate' => 0,
        'deduction' => 0,
    ]);

    $employee = Employee::create([
        'employee_id' => 'EMP-FIN-PENALTY',
        'attendance_device_id' => '502',
        'first_name' => 'Abdulkerim',
        'last_name' => 'Abdulmelik',
        'phone' => '0911555502',
        'hire_date' => '2026-01-01',
        'status' => 'active',
        'basic_salary' => 15432.50,
        'overtime_multiplier' => 1,
        'pension_enabled' => false,
    ]);

    AttendancePeriodSummary::create([
        'employee_id' => $employee->id,
        'period_start' => '2026-05-01',
        'period_end' => '2026-05-31',
        'work_days' => 26,
        'actual_days' => 26,
        'late_minutes' => 720,
        'work_time_hours' => 208,
    ]);

    $payrollRun = PayrollRun::create([
        'name' => 'Penalty sample payroll',
        'period_start' => '2026-05-01',
        'period_end' => '2026-05-31',
    ]);

    app(CalculatePayrollRun::class)->handle($payrollRun, [$employee->id]);

    $payrollEmployee = $payrollRun->employees()->firstOrFail();

    expect((float) $payrollEmployee->penalty_hours)->toBe(12.0)
        ->and((float) $payrollEmployee->penalty_amount)->toBe(771.63)
        ->and(round((float) $payrollEmployee->calculation_snapshot['penalty_hourly_rate'], 4))->toBe(64.3021);
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
        'account_holder_name' => 'Packledge',
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

it('prunes zero net employees before approving payroll', function () {
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
        'loan' => 500,
        'total_deduction' => 500,
        'net_pay' => 0,
    ]));

    $journalEntry = app(PostPayrollRun::class)->handle($payrollRun);

    expect($payrollRun->refresh()->employees()->pluck('employee_id')->all())->toBe([$payableEmployee->id])
        ->and($payrollRun->selected_employee_ids)->toBe([$payableEmployee->id])
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

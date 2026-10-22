<?php

use App\Exceptions\HrWorkflowException;
use App\Filament\Imports\AttendanceSegmentImporter;
use App\Filament\Resources\EmployeeLoans\Pages\ManageEmployeeLoans;
use App\Filament\Resources\LeaveRequests\Pages\ManageLeaveRequests;
use App\Filament\Resources\PayrollRuns\Pages\EditPayrollRun;
use App\Filament\Resources\PayrollRuns\Pages\ListPayrollRuns;
use App\Livewire\ExceptionHandlerHook;
use App\Models\AttendanceDailySummary;
use App\Models\AttendancePeriodSummary;
use App\Models\AttendanceSegment;
use App\Models\Bank;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Payment;
use App\Models\PayrollRun;
use App\Models\PayrollRunEmployee;
use App\Models\User;
use App\Services\Accounting\VoidPaymentJournalEntry;
use App\Services\Hr\CalculatePayrollRun;
use App\Services\Hr\CalculatePayrollTax;
use App\Services\Hr\CorrectPayrollRun;
use App\Services\Hr\DisburseEmployeeLoan;
use App\Services\Hr\ExportPayrollBankAdvice;
use App\Services\Hr\ExportPayrollDocuments;
use App\Services\Hr\ManagePayrollPaymentBatch;
use App\Services\Hr\PostPayrollRun;
use App\Services\Hr\PrepareMonthlyPayrollRun;
use App\Services\Hr\RebuildAttendanceDailySummaries;
use App\Services\Hr\RecalculatePayrollRegisterRow;
use App\Services\Hr\RecordPayrollRemittance;
use App\Services\Hr\RepayEmployeeLoan;
use App\Services\Hr\ReviewLeaveRequest;
use App\Services\Hr\ReviseEmployeeSalary;
use App\UserRole;
use Carbon\CarbonImmutable;
use Database\Seeders\PayrollTaxRuleSeeder;
use Filament\Actions\Imports\Models\Import;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    expect(app()->environment())->toBe('testing');
    if (config('database.default') === 'mysql') {
        expect(config('database.connections.mysql.database'))->toBe('printos_audit_testing');
    } else {
        expect(config('database.default'))->toBe('sqlite');
        expect(config('database.connections.sqlite.database'))->toBe(':memory:');
    }
    $this->seed(PayrollTaxRuleSeeder::class);
});

it('audit: fractional taxable income matches a bracket', function () {
    expect(app(CalculatePayrollTax::class)->handle(600.50, '2026-05-31')['amount'])->toBeGreaterThanOrEqual(0);
});

it('audit: bonus edits select the new applicable tax bracket', function () {
    $tax = app(CalculatePayrollTax::class);
    $snapshot = ['tax' => $tax->handle(1000, '2026-05-31'), 'pension_enabled' => false, 'union_enabled' => false];
    $row = app(RecalculatePayrollRegisterRow::class)->forData(['basic_salary' => 1000, 'bonus' => 11000, 'calculation_snapshot' => $snapshot], PayrollRun::factory()->create());
    expect($row['income_tax'])->toBe($tax->handle(12000, '2026-05-31')['amount']);
});

it('audit: leave minutes are divided over the original leave dates', function () {
    $employee = Employee::factory()->create();
    $type = LeaveType::create(['name' => 'Paid audit leave', 'is_paid' => true]);
    $employee->leaveRequests()->create(['leave_type_id' => $type->id, 'start_date' => '2026-05-30', 'end_date' => '2026-06-02', 'minutes' => 1920, 'status' => 'approved']);
    $run = app(CalculatePayrollRun::class)->handle(PayrollRun::factory()->create(), [$employee->id]);
    expect($run->employees()->firstOrFail()->calculation_snapshot['paid_leave_minutes'])->toBe(960);
});

it('audit: approved payroll settings cannot change when preparation fails', function () {
    $run = PayrollRun::factory()->create(['status' => 'approved', 'period_type' => 'monthly', 'payroll_month' => '2026-06-01']);
    $before = $run->period_start->toDateString();
    try {
        app(PrepareMonthlyPayrollRun::class)->handle($run);
    } catch (RuntimeException) {
    }
    expect($run->refresh()->period_start->toDateString())->toBe($before);
});

it('audit: overlapping payroll runs cannot both pay the same employment period', function () {
    $employee = Employee::factory()->create();
    AttendancePeriodSummary::create(['employee_id' => $employee->id, 'period_start' => '2026-05-01', 'period_end' => '2026-05-31', 'shift_type' => 'normal', 'work_days' => 26, 'actual_days' => 26]);
    $calculator = app(CalculatePayrollRun::class);
    $first = $calculator->handle(PayrollRun::factory()->create(), [$employee->id]);
    $first->update(['attendance_reviewed_at' => now()]);
    app(PostPayrollRun::class)->handle($first);
    $second = $calculator->handle(PayrollRun::factory()->create(), [$employee->id]);
    $second->update(['attendance_reviewed_at' => now()]);
    expect(fn () => app(PostPayrollRun::class)->handle($second))->toThrow(RuntimeException::class);
});

it('audit: split shift import preserves both segments', function () {
    $employee = Employee::factory()->create(['attendance_device_id' => '12']);
    $row = ['date' => '2026-05-04', 'fp_no' => '12', 'schedule_name' => 'Morning', 'scheduled_start' => '08:00:00', 'scheduled_end' => '12:00:00', 'clock_in' => '08:00:00', 'clock_out' => '12:00:00', 'late_minutes' => '0', 'early_minutes' => '0', 'worked_minutes' => '4:00', 'overtime_minutes' => '0', 'day_fraction' => '0.5', 'status' => 'Present'];
    $map = array_combine(array_keys($row), array_keys($row));
    $importer = new AttendanceSegmentImporter(new Import, $map, []);
    $importer($row);
    $row = [...$row, 'schedule_name' => 'Afternoon', 'scheduled_start' => '13:00:00', 'scheduled_end' => '17:00:00', 'clock_in' => '13:00:00', 'clock_out' => '17:00:00'];
    $importer($row);
    expect(AttendanceSegment::where('employee_id', $employee->id)->count())->toBe(2);
});

it('audit: refreshing the roster preserves reviewed manual bonuses', function () {
    $employee = Employee::factory()->create();
    AttendancePeriodSummary::create(['employee_id' => $employee->id, 'period_start' => '2026-05-01', 'period_end' => '2026-05-31', 'shift_type' => 'normal', 'work_days' => 26, 'actual_days' => 26]);
    $run = app(CalculatePayrollRun::class)->handle(PayrollRun::factory()->create(), [$employee->id]);
    $run->employees()->firstOrFail()->update(['bonus' => 200]);
    $run = app(CalculatePayrollRun::class)->handle($run, [$employee->id]);
    expect((float) $run->employees()->firstOrFail()->bonus)->toBe(200.0);
});

it('audit: daily attendance prorates a midmonth hire against the full payroll period', function () {
    $employee = Employee::factory()->create(['hire_date' => '2026-05-16']);
    $day = CarbonImmutable::parse('2026-05-16');
    while ($day->lte(CarbonImmutable::parse('2026-05-31'))) {
        if (! $day->isSunday()) {
            AttendanceDailySummary::create(['employee_id' => $employee->id, 'date' => $day->toDateString(), 'expected_minutes' => 480, 'worked_minutes' => 480, 'regular_minutes' => 480, 'status' => 'present']);
        }
        $day = $day->addDay();
    }
    $run = app(CalculatePayrollRun::class)->handle(PayrollRun::factory()->create(), [$employee->id]);
    $row = $run->employees()->firstOrFail();
    $snapshot = $row->calculation_snapshot;
    expect((float) $row->basic_salary)->toBe(round(12000 * $snapshot['employment_business_days'] / $snapshot['period_business_days'], 2));
});

function hrApprovedRun(): PayrollRun
{
    $employee = Employee::factory()->create(['bank_name' => 'Audit Bank', 'account_number' => '12345678']);
    $run = PayrollRun::factory()->create();
    AttendancePeriodSummary::create(['employee_id' => $employee->id, 'period_start' => '2026-05-01', 'period_end' => '2026-05-31', 'shift_type' => 'normal', 'work_days' => 26, 'actual_days' => 26]);
    $run = app(CalculatePayrollRun::class)->handle($run, [$employee->id]);
    $run->update(['attendance_reviewed_at' => now(), 'attendance_reviewed_by' => auth()->id()]);
    app(PostPayrollRun::class)->handle($run);

    return $run->refresh();
}

it('requires attendance review and a separate approver for ordinary finance users', function () {
    $user = User::factory()->create(['role' => UserRole::Finance]);
    $this->actingAs($user);
    $run = PayrollRun::factory()->create(['created_by' => $user->id]);
    expect(Gate::allows('approve', $run))->toBeFalse();
    $run->update(['created_by' => null]);
    expect(fn () => app(PostPayrollRun::class)->handle($run))->toThrow(RuntimeException::class, 'Confirm attendance review');
});

it('prepares and settles a snapshot payment batch only once', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Finance]));
    $run = hrApprovedRun();
    $bank = Bank::factory()->create(['current_balance' => 100000, 'status' => 'active']);
    $service = app(ManagePayrollPaymentBatch::class);
    $batch = $service->prepare($run, $bank);
    $original = $batch->beneficiaries[0]['account_number'];
    $run->employees()->first()->employee->update(['account_number' => 'CHANGED']);
    expect($batch->fresh()->beneficiaries[0]['account_number'])->toBe($original)
        ->and($run->refresh()->status)->toBe('approved')
        ->and(Payment::where('payment_type', 'payroll')->count())->toBe(0);
    expect(fn () => $service->prepare($run, $bank))->toThrow(RuntimeException::class);
    $service->transition($batch, 'submitted', 'BANK-SUBMIT');
    $service->transition($batch->fresh(), 'settled', 'BANK-SETTLED');
    expect($run->fresh()->status)->toBe('paid')
        ->and((float) $bank->fresh()->current_balance)->toBe(round(100000 - (float) $batch->amount, 2));
    $csv = app(ExportPayrollBankAdvice::class)->csv($run->fresh(), $bank, $batch->id);
    expect($csv)->toContain($original)->not->toContain('CHANGED');
    expect(fn () => $service->transition($batch->fresh(), 'settled', 'DUPLICATE'))->toThrow(RuntimeException::class);
    $payment = Payment::findOrFail($batch->fresh()->payment_id);
    expect($payment->payment_date->toDateString())->toBe(today()->toDateString());
    app(VoidPaymentJournalEntry::class)->handle($payment, 'Wrong bank');
    expect($batch->fresh()->status)->toBe('reversed')->and($run->fresh()->status)->toBe('approved');
});

it('rejects missing beneficiary details and failed batch settlement rolls back', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Finance]));
    $run = hrApprovedRun();
    $bank = Bank::factory()->create(['current_balance' => 1, 'status' => 'active']);
    $service = app(ManagePayrollPaymentBatch::class);
    $batch = $service->prepare($run, $bank);
    $service->transition($batch, 'submitted', 'SUBMIT');
    expect(fn () => $service->transition($batch->fresh(), 'settled', 'FAIL'))->toThrow(RuntimeException::class);
    expect($batch->fresh()->status)->toBe('submitted')->and($run->fresh()->status)->toBe('approved')->and(Payment::count())->toBe(0);
    $service->transition($batch->fresh(), 'failed', 'BANK-REJECT', 'Insufficient funds');
    expect($service->prepare($run, $bank)->id)->not->toBe($batch->id);
});

it('requires approved funded loans before payroll recovery and reverses disbursement safely', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Finance]));
    $loan = Employee::factory()->create()->loans()->create(['loan_date' => '2026-05-01', 'return_date' => '2026-05-31', 'amount' => 100, 'installment_count' => 1]);
    expect(fn () => app(RepayEmployeeLoan::class)->handle($loan, 'cash'))->toThrow(RuntimeException::class);
    expect(fn () => app(DisburseEmployeeLoan::class)->handle($loan, 'cash'))->toThrow(RuntimeException::class);
    $loan->update(['status' => 'approved']);
    $payment = app(DisburseEmployeeLoan::class)->handle($loan, 'cash');
    expect($loan->fresh()->isFunded())->toBeTrue();
    expect(fn () => app(DisburseEmployeeLoan::class)->handle($loan->fresh(), 'cash'))->toThrow(RuntimeException::class);
    app(VoidPaymentJournalEntry::class)->handle($payment, 'Cancelled disbursement');
    expect($loan->fresh()->isFunded())->toBeFalse()->and($loan->fresh()->status)->toBe('approved');
});

it('enforces leave decisions overlaps and annual balances', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::HR]));
    $employee = Employee::factory()->create();
    $type = LeaveType::create(['name' => 'Annual', 'is_paid' => true, 'annual_allowance_days' => 2]);
    $attributes = ['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'start_date' => '2026-05-04', 'end_date' => '2026-05-04', 'minutes' => 480, 'status' => 'pending'];
    $request = LeaveRequest::create($attributes);
    $service = app(ReviewLeaveRequest::class);
    $service->handle($request, 'approved', 'Coverage arranged');
    expect($request->fresh()->approved_by)->toBe(auth()->id())->and($service->balance($employee->id, $type, 2026))->toBe(480);
    $overlap = LeaveRequest::create($attributes);
    expect(fn () => $service->handle($overlap, 'approved', 'Overlap'))->toThrow(ValidationException::class);
    $overlap->update(['start_date' => '2026-05-06', 'end_date' => '2026-05-07', 'minutes' => 960]);
    expect(fn () => $service->handle($overlap, 'approved', 'Too much'))->toThrow(ValidationException::class);
    $service->handle($overlap, 'rejected', 'Insufficient balance');
    expect($overlap->fresh()->status)->toBe('rejected');
});

it('reverses an unpaid payroll into a traceable correction without deleting its records', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Finance]));
    $run = hrApprovedRun();
    $rows = $run->employees()->count();
    $draft = app(CorrectPayrollRun::class)->handle($run, 'Wrong salary input');
    expect($run->fresh()->status)->toBe('reversed')->and($run->employees()->count())->toBe($rows)
        ->and($draft->status)->toBe('draft')->and($draft->employees()->count())->toBe($rows)
        ->and($run->journalEntry->fresh()->voided_at)->not->toBeNull();
    expect(fn () => app(CorrectPayrollRun::class)->handle($run->fresh(), 'Again'))->toThrow(RuntimeException::class);
});

it('records partial remittances against liabilities and restores them on reversal', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Finance]));
    $run = hrApprovedRun();
    $bank = Bank::factory()->create(['current_balance' => 100000]);
    $service = app(RecordPayrollRemittance::class);
    $due = $service->outstanding($run, 'payroll_tax_remittance');
    $payment = $service->handle($run, 'payroll_tax_remittance', 100, $bank->id, 'TAX-RECEIPT');
    expect($service->outstanding($run, 'payroll_tax_remittance'))->toBe($due - 100);
    expect(fn () => $service->handle($run, 'payroll_tax_remittance', $due, $bank->id, 'OVERPAY'))->toThrow(RuntimeException::class);
    app(VoidPaymentJournalEntry::class)->handle($payment, 'Wrong reference');
    expect($service->outstanding($run, 'payroll_tax_remittance'))->toBe($due);
});

it('denies HR access to payroll exports and finance salary editing', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::HR]));
    $run = PayrollRun::factory()->create();
    expect(Gate::allows('view', $run))->toBeFalse();
    expect(fn () => app(ExportPayrollDocuments::class)->remittances($run))->toThrow(AuthorizationException::class);
    $this->actingAs(User::factory()->create(['role' => UserRole::Finance]));
    expect(Gate::allows('update', Employee::factory()->create()))->toBeFalse();
});

it('records effective dated salary revisions without rewriting past rates', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::HR]));
    $employee = Employee::factory()->create(['basic_salary' => 1000, 'hire_date' => '2026-01-01']);
    app(ReviseEmployeeSalary::class)->handle($employee, 2000, '2026-06-01', 'Promotion');
    expect((float) $employee->salaryHistories()->whereDate('effective_date', '<=', '2026-05-31')->latest('effective_date')->value('basic_salary'))->toBe(1000.0);
    expect($employee->salaryHistories()->where('change_reason', 'like', 'Promotion%')->count())->toBe(1);
});

it('retains a zero net row with liabilities and issues an itemised payslip', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Finance]));
    $run = PayrollRun::factory()->create(['attendance_reviewed_at' => now()]);
    $employee = Employee::factory()->create();
    $row = PayrollRunEmployee::withoutEvents(fn () => $run->employees()->create(['employee_id' => $employee->id, 'basic_salary' => 600, 'gross_earning' => 600, 'income_tax' => 600, 'total_deduction' => 600, 'net_pay' => 0]));
    $row->lineItems()->create(['type' => 'earning', 'code' => 'basic_salary', 'description' => 'Basic salary', 'amount' => 600]);
    app(PostPayrollRun::class)->handle($run);
    expect($run->employees()->count())->toBe(1)->and((float) $run->journalEntry->journalItems()->whereHas('account', fn ($query) => $query->where('code', '2160'))->sum('credit'))->toBe(600.0);
    $response = app(ExportPayrollDocuments::class)->payslip($row->fresh());
    ob_start();
    $response->sendContent();
    $csv = ob_get_clean();
    expect($csv)->toContain('Basic salary')->toContain('Net pay');
});

it('restores precisely allocated payroll loan recoveries during correction', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Finance]));
    $run = hrApprovedRun();
    $draft = app(CorrectPayrollRun::class)->handle($run, 'Include loan');
    $employee = $draft->employees()->first()->employee;
    $loan = $employee->loans()->create(['loan_date' => '2026-05-01', 'return_date' => '2026-05-31', 'amount' => 100, 'installment_count' => 1, 'status' => 'active', 'legacy_disbursement_confirmed' => true]);
    $draft = app(CalculatePayrollRun::class)->handle($draft, [$employee->id]);
    $draft->update(['attendance_reviewed_at' => now()]);
    $this->actingAs(User::factory()->create(['role' => UserRole::Finance]));
    app(PostPayrollRun::class)->handle($draft);
    expect($loan->fresh()->remainingBalance())->toBe(0.0);
    app(CorrectPayrollRun::class)->handle($draft->fresh(), 'Reverse recovery');
    expect($loan->fresh()->remainingBalance())->toBe(100.0);
});

it('paginates the payroll workspace and keeps list query growth bounded', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Finance]));
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('finance'));
    $run = PayrollRun::factory()->create();
    foreach (Employee::factory()->count(30)->create() as $employee) {
        PayrollRunEmployee::withoutEvents(fn () => $run->employees()->create(['employee_id' => $employee->id, 'net_pay' => 100, 'gross_earning' => 100]));
    }
    $workspace = Livewire\Livewire::test(EditPayrollRun::class, ['record' => $run->id]);
    expect($workspace->instance()->payrollRows()->count())->toBe(25)->and($workspace->instance()->payrollRows()->total())->toBe(30)->and($workspace->instance()->summary()['employee_count'])->toBe(30);
    DB::enableQueryLog();
    DB::flushQueryLog();
    Livewire\Livewire::test(ListPayrollRuns::class);
    $small = count(DB::getQueryLog());
    PayrollRun::factory()->count(15)->create();
    DB::flushQueryLog();
    Livewire\Livewire::test(ListPayrollRuns::class);
    $queries = DB::getQueryLog();
    $large = count($queries);
    DB::disableQueryLog();
    expect($large)->toBeLessThanOrEqual($small + 3, json_encode(array_column($queries, 'query')));
});

it('renders leave decisions and loan disbursement actions in their permitted panels', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::HR]));
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('hr'));
    Livewire\Livewire::test(ManageLeaveRequests::class)->assertSuccessful();
    $this->actingAs(User::factory()->create(['role' => UserRole::Finance]));
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('finance'));
    Livewire\Livewire::test(ManageEmployeeLoans::class)->assertSuccessful();
    $this->artisan('hr:audit-records')->expectsOutput('No records were changed.')->assertSuccessful();
});

it('derives half day leave minutes from times and moves attendance summaries on correction', function () {
    $employee = Employee::factory()->create();
    $type = LeaveType::create(['name' => 'Paid time', 'is_paid' => true]);
    $request = $employee->leaveRequests()->create(['leave_type_id' => $type->id, 'start_date' => '2026-05-04', 'end_date' => '2026-05-04', 'start_time' => '08:00:00', 'end_time' => '12:00:00', 'status' => 'pending']);
    expect((int) $request->minutes)->toBe(240);
    $segment = AttendanceSegment::create(['employee_id' => $employee->id, 'date' => '2026-05-04', 'fp_no' => $employee->attendance_device_id, 'day_fraction' => 1, 'worked_minutes' => 480, 'status' => 'Present']);
    app(RebuildAttendanceDailySummaries::class)->forSegments(collect([$segment]));
    $segment->update(['date' => '2026-05-05']);
    expect(AttendanceDailySummary::where('employee_id', $employee->id)->whereDate('date', '2026-05-04')->exists())->toBeFalse()
        ->and(AttendanceDailySummary::where('employee_id', $employee->id)->whereDate('date', '2026-05-05')->count())->toBe(1);
});

it('blocks approval when attendance inputs changed after calculation', function () {
    $run = PayrollRun::factory()->create();
    $employee = Employee::factory()->create();
    $summary = AttendancePeriodSummary::create(['employee_id' => $employee->id, 'period_start' => '2026-05-01', 'period_end' => '2026-05-31', 'shift_type' => 'normal', 'work_days' => 26, 'actual_days' => 26]);
    app(CalculatePayrollRun::class)->handle($run);
    $run->update(['attendance_reviewed_at' => now()]);
    $summary->update(['actual_days' => 20]);
    expect(fn () => app(PostPayrollRun::class)->handle($run))->toThrow(RuntimeException::class, 'Payroll inputs changed');
    expect($run->fresh()->status)->toBe('draft');
});

it('shows a specific safe payroll blocker to the user', function () {
    $stopped = false;
    (new ExceptionHandlerHook)->exception(new HrWorkflowException('Confirm attendance review before approving payroll.'), function () use (&$stopped): void {
        $stopped = true;
    });
    Notification::assertNotified(Notification::make()->title('Something went wrong')->body('Confirm attendance review before approving payroll.')->danger()->persistent());
    expect($stopped)->toBeTrue();
});

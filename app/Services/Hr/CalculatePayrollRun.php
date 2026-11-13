<?php

namespace App\Services\Hr;

use App\Exceptions\HrWorkflowException;
use App\Models\Employee;
use App\Models\EmployeeLoanInstallment;
use App\Models\PayrollOvertimeEntry;
use App\Models\PayrollRun;
use App\Models\PayrollRunEmployee;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CalculatePayrollRun
{
    public function __construct(
        private CalculatePayrollTax $taxCalculator,
        private BuildScheduledAttendance $attendance,
        private CalculateSalaryEntitlement $salary,
        private GeneratePayrollOvertimeEntries $overtimeEntries,
    ) {}

    /** @param list<int>|null $employeeIds */
    public function handle(PayrollRun $run, ?array $employeeIds = null): PayrollRun
    {
        return DB::transaction(function () use ($run, $employeeIds): PayrollRun {
            $run = PayrollRun::query()->lockForUpdate()->findOrFail($run->id);
            if ($run->status !== 'draft') {
                throw new HrWorkflowException('Only draft payroll runs can be recalculated.');
            }
            $start = $run->period_start->toDateString();
            $end = $run->period_end->toDateString();
            $employees = Employee::whereDate('hire_date', '<=', $end)
                ->where(fn ($query) => $query->whereRaw('LOWER(status) = ?', ['active'])->orWhereDate('termination_date', '>=', $start))
                ->when($employeeIds !== null, fn ($query) => $query->whereIn('id', $employeeIds))->get();
            $run->update(['attendance_reviewed_at' => null, 'attendance_reviewed_by' => null, 'attendance_review_note' => null]);
            $removedRowIds = $run->employees()->whereNotIn('employee_id', $employees->modelKeys())->pluck('id');
            PayrollOvertimeEntry::whereIn('payroll_run_employee_id', $removedRowIds)->update(['payroll_run_id' => null, 'payroll_run_employee_id' => null]);
            $run->employees()->whereNotIn('employee_id', $employees->modelKeys())->delete();
            foreach ($employees as $employee) {
                $this->calculateEmployee($run, $employee);
            }

            return $run->refresh();
        }, 3);
    }

    private function calculateEmployee(PayrollRun $run, Employee $employee): PayrollRunEmployee
    {
        $start = $run->period_start->toDateString();
        $end = $run->period_end->toDateString();
        $summaries = $this->attendance->handle($employee, $start, $end);
        $salary = $this->salary->handle($employee, $run, $summaries);
        $overtime = $this->overtimeEntries->approvedTotals($run, $employee);
        foreach (PayrollOvertimeEntry::whereIn('id', $overtime['ids'])->get() as $entry) {
            $source = $summaries->first(fn ($summary): bool => $summary->date->toDateString() === ($entry->calculation_snapshot['work_date'] ?? null));
            if (! $source || ($entry->calculation_snapshot['source_signature'] ?? null) !== GeneratePayrollOvertimeEntries::sourceSignature($source)) {
                $salary['issues'][] = $entry->date->toDateString().': approved overtime source changed. Regenerate and review overtime.';
            }
        }
        $row = $run->employees()->where('employee_id', $employee->id)->first() ?? new PayrollRunEmployee(['payroll_run_id' => $run->id, 'employee_id' => $employee->id]);
        $previous = $row->calculation_snapshot ?? [];
        $manualEarnings = $previous['manual_earnings'] ?? [];
        $manualDeductions = $previous['manual_deductions'] ?? [];
        $bonus = (float) ($row->bonus ?? 0);
        $settings = Setting::getSettings();
        $employeeRate = (float) $settings->employee_pension_rate;
        $employerRate = (float) $settings->employer_pension_rate;
        $employerPension = round($salary['pension_base'] * $employerRate / 100, 2);
        $employeePension = round($salary['pension_base'] * $employeeRate / 100, 2);
        $gross = round($salary['basic'] + $salary['allowance'] + $overtime['amount'] + $bonus + collect($manualEarnings)->sum('amount'), 2);
        $tax = $this->taxCalculator->handle(max(0, $gross), $end);
        $installments = $this->loanInstallments($employee, $start, $end);
        $loan = round($installments->sum(fn ($item): float => max(0, (float) $item->amount - (float) $item->paid_amount)), 2);
        $union = $settings->workers_union_enabled ? round($salary['basic'] * (float) $settings->workers_union_rate / 100, 2) : 0;
        $deductions = round($tax['amount'] + $employeePension + $loan + $union + collect($manualDeductions)->sum('amount'), 2);
        $row->fill([
            'basic_salary' => $salary['basic'], 'transport_allowance' => $salary['allowance'], 'bonus' => $bonus,
            'time_on_duty' => round($summaries->sum(fn ($summary): int => (int) ($summary->calculation_snapshot['total_worked_minutes'] ?? 0)) / 60, 2),
            'pay_per_hour' => round($salary['hourly_rate'], 4), 'employer_pension_contribution' => $employerPension,
            'overtime_hours' => $overtime['hours'], 'overtime_amount' => $overtime['amount'],
            'gross_earning' => $gross, 'taxable_amount' => max(0, $gross), 'income_tax' => $tax['amount'],
            'penalty_hours' => 0, 'penalty_amount' => 0, 'pension_contribution' => $employeePension,
            'loan' => $loan, 'workers_union' => $union, 'total_deduction' => $deductions, 'net_pay' => max(0, round($gross - $deductions, 2)),
            'calculation_snapshot' => [
                'version' => 2, 'input_signature' => PayrollInputs::signature($employee, $start, $end),
                'salary_portions' => $salary['portions'], 'attendance' => $summaries->map->attributesToArray()->all(),
                'attendance_issues' => $salary['issues'], 'unpaid_minutes' => $salary['unpaid_minutes'],
                'paid_leave_minutes' => (int) $summaries->sum('paid_leave_minutes'), 'unpaid_leave_minutes' => (int) $summaries->sum('unpaid_leave_minutes'),
                'unpaid_time_reduction' => $salary['unpaid_reduction'], 'salary_entitlement' => round($salary['basic'] + $salary['unpaid_reduction'], 2),
                'full_basic_salary' => $this->salary->salaryOn($employee, $end), 'pension_base' => $salary['pension_base'],
                'base_days' => $salary['portions'][0]['divisor'] ?? 30, 'pension_enabled' => $salary['pension_base'] > 0,
                'employee_pension_rate' => $employeeRate, 'employer_pension_rate' => $employerRate,
                'union_enabled' => (bool) $settings->workers_union_enabled, 'union_rate' => (float) $settings->workers_union_rate,
                'approved_overtime_entry_ids' => $overtime['ids'],
                'approved_overtime' => PayrollOvertimeEntry::whereIn('id', $overtime['ids'])->get()->map->attributesToArray()->all(),
                'loan_installment_ids' => $installments->modelKeys(), 'loan_ids' => $installments->pluck('employee_loan_id')->unique()->values()->all(),
                'manual_earnings' => $manualEarnings, 'manual_deductions' => $manualDeductions, 'tax' => $tax,
            ],
        ])->save();
        $this->overtimeEntries->linkEntriesToPayrollRow($row);
        $row->lineItems()->delete();
        $this->createLineItems($row);

        return $row;
    }

    private function loanInstallments(Employee $employee, string $periodStart, string $periodEnd): Collection
    {
        return EmployeeLoanInstallment::query()
            ->whereHas('loan', fn ($query) => $query
                ->where('employee_id', $employee->id)
                ->whereIn('status', ['active', 'partially_paid'])
                ->where(fn ($funding) => $funding->where('legacy_disbursement_confirmed', true)->orWhereHas('disbursementPayment', fn ($payment) => $payment->whereNull('voided_at'))))
            ->dueForPeriod($periodStart, $periodEnd)
            ->get();
    }

    private function createLineItems(PayrollRunEmployee $employee): void
    {
        foreach ([
            ['earning', 'basic_salary', 'Basic salary', $employee->basic_salary],
            ['earning', 'bonus', 'Bonus', $employee->bonus],
            ['earning', 'transport_allowance', 'Transport allowance', $employee->transport_allowance],
            ['employer_cost', 'employer_pension', 'Employer pension contribution', $employee->employer_pension_contribution],
            ['earning', 'overtime_amount', 'Overtime', $employee->overtime_amount],
            ['deduction', 'income_tax', 'Income tax', $employee->income_tax],
            ['deduction', 'penalty', 'Penalty', $employee->penalty_amount],
            ['deduction', 'pension', 'Pension contribution', $employee->pension_contribution],
            ['deduction', 'loan', 'Loan / recurring deductions', $employee->loan],
            ['deduction', 'workers_union', 'Workers union', $employee->workers_union],
        ] as [$type, $code, $description, $amount]) {
            if ((float) $amount === 0.0) {
                continue;
            }

            $employee->lineItems()->create([
                'type' => $type,
                'code' => $code,
                'description' => $description,
                'amount' => $amount,
            ]);
        }

        foreach ($employee->calculation_snapshot['manual_earnings'] ?? [] as $item) {
            if ((float) ($item['amount'] ?? 0) === 0.0) {
                continue;
            }

            $employee->lineItems()->create([
                'type' => 'earning',
                'code' => $item['code'] ?? 'other_earning',
                'description' => $item['description'] ?? 'Other earning',
                'amount' => (float) ($item['amount'] ?? 0),
            ]);
        }

        foreach ($employee->calculation_snapshot['manual_deductions'] ?? [] as $item) {
            if ((float) ($item['amount'] ?? 0) === 0.0) {
                continue;
            }

            $employee->lineItems()->create([
                'type' => 'deduction',
                'code' => $item['code'] ?? 'other_deduction',
                'description' => $item['description'] ?? 'Other deduction',
                'amount' => (float) ($item['amount'] ?? 0),
            ]);
        }
    }
}

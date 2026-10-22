<?php

namespace App\Services\Hr;

use App\Exceptions\HrWorkflowException;
use App\Models\Account;
use App\Models\Employee;
use App\Models\EmployeeLoanInstallment;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\PayrollRun;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PostPayrollRun
{
    public function handle(PayrollRun $payrollRun): JournalEntry
    {
        if (auth()->check()) {
            Gate::authorize('approve', $payrollRun);
        }
        $journalEntry = DB::transaction(function () use ($payrollRun): JournalEntry {
            $payrollRun = PayrollRun::query()->lockForUpdate()->findOrFail($payrollRun->id);
            $existingJournalEntry = JournalEntry::query()
                ->where('source_type', PayrollRun::class)->where('source_id', $payrollRun->id)
                ->whereNull('reversal_of_journal_entry_id')->whereNull('voided_at')->first();
            if ($existingJournalEntry) {
                return $existingJournalEntry;
            }
            if ($payrollRun->status !== 'draft') {
                throw new HrWorkflowException('Only draft payroll runs can be approved.');
            }
            if (! $payrollRun->attendance_reviewed_at) {
                throw new HrWorkflowException('Confirm attendance review before approving payroll.');
            }
            if ($payrollRun->overtimeEntries()->where('status', 'pending')->exists()) {
                throw new HrWorkflowException('Approve or reject all overtime candidates before approving payroll.');
            }
            $payrollRun->loadMissing('employees');
            Employee::query()->whereIn('id', $payrollRun->employees->pluck('employee_id'))->orderBy('id')->lockForUpdate()->get();
            $overlap = PayrollRun::query()->whereKeyNot($payrollRun->id)->whereIn('status', ['approved', 'paid'])
                ->whereDate('period_start', '<=', $payrollRun->period_end)->whereDate('period_end', '>=', $payrollRun->period_start)
                ->whereHas('employees', fn ($query) => $query->whereIn('employee_id', $payrollRun->employees->pluck('employee_id')))
                ->lockForUpdate()->first();
            if ($overlap) {
                throw new HrWorkflowException('An approved payroll already covers an employee in this period. Use a correction instead.');
            }
            $payrollRun->load('employees.employee');
            (new Employee)->newCollection($payrollRun->employees->pluck('employee')->all())->load(PayrollInputs::relations($payrollRun->period_start->toDateString(), $payrollRun->period_end->toDateString()));
            $settings = Setting::getSettings();
            foreach ($payrollRun->employees as $row) {
                $snapshot = $row->calculation_snapshot ?? [];
                if (isset($snapshot['input_signature']) && $snapshot['input_signature'] !== PayrollInputs::signature($row->employee, $payrollRun->period_start->toDateString(), $payrollRun->period_end->toDateString())) {
                    throw new HrWorkflowException('Payroll inputs changed. Recalculate and review attendance before approval.');
                }
                $snapshot['currency_code'] = $settings->currency_code ?: 'ETB';
                $snapshot['employer'] = ['company_name' => $settings->company_name, 'tax_id' => $settings->company_tax_id];
                $snapshot['beneficiary'] = ['employee_id' => $row->employee?->employee_id, 'name' => $row->employee?->full_name, 'position' => $row->employee?->position, 'bank_name' => $row->employee?->bank_name, 'account_number' => $row->employee?->account_number];
                $row->forceFill(['calculation_snapshot' => $snapshot])->saveQuietly();
            }

            if ($payrollRun->employees->isEmpty() || (float) $payrollRun->employees->sum('gross_earning') <= 0) {
                throw new HrWorkflowException('Payroll must have calculated employee net pay before posting.');
            }

            $grossEarning = (float) $payrollRun->employees->sum('gross_earning');
            $overtime = (float) $payrollRun->employees->sum('overtime_amount');
            $employerPension = (float) $payrollRun->employees->sum('employer_pension_contribution');
            $salaryExpense = round($grossEarning - $overtime - $employerPension, 2);
            $netPay = (float) $payrollRun->employees->sum('net_pay');
            $incomeTax = (float) $payrollRun->employees->sum('income_tax');
            $penalty = (float) $payrollRun->employees->sum('penalty_amount');
            $pension = (float) $payrollRun->employees->sum('pension_contribution');
            $loan = (float) $payrollRun->employees->sum('loan');
            $workersUnion = (float) $payrollRun->employees->sum('workers_union');
            $manualDeductions = $payrollRun->employees->sum(fn ($row): float => (float) collect($row->calculation_snapshot['manual_deductions'] ?? [])->sum(fn (array $item): float => max(0, (float) ($item['amount'] ?? 0))));

            $totalDebit = round($salaryExpense + $overtime + $employerPension, 2);
            $totalCredit = round($netPay + $incomeTax + $penalty + $pension + $loan + $workersUnion + $manualDeductions, 2);

            if ($totalDebit !== $totalCredit) {
                throw new HrWorkflowException('Payroll journal must balance before posting.');
            }

            $journalEntry = JournalEntry::create([
                'date' => $payrollRun->pay_date ?? $payrollRun->period_end,
                'reference' => 'Payroll #'.$payrollRun->id,
                'source_type' => PayrollRun::class,
                'source_id' => $payrollRun->id,
                'narration' => $payrollRun->name,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'status' => 'posted',
                'posted_at' => now(),
            ]);

            $this->item($journalEntry, '5100', 'Salary Expense', 'Expense', $salaryExpense, 0);
            $this->item($journalEntry, '5110', 'Overtime Expense', 'Expense', $overtime, 0);
            $this->item($journalEntry, '5120', 'Pension Expense', 'Expense', $employerPension, 0);
            $this->item($journalEntry, '2150', 'Payroll Payable', 'Liability', 0, $netPay);
            $this->item($journalEntry, '2160', 'PAYE Tax Payable', 'Liability', 0, $incomeTax);
            $this->item($journalEntry, '2165', 'Payroll Penalty Clearing', 'Liability', 0, $penalty);
            $this->item($journalEntry, '2170', 'Pension Payable', 'Liability', 0, $pension);
            $this->item($journalEntry, '1230', 'Employee Loans Receivable', 'Asset', 0, $loan);
            $this->item($journalEntry, '2190', 'Workers Union Payable', 'Liability', 0, $workersUnion);

            $this->item($journalEntry, '2195', 'Payroll Deductions Clearing', 'Liability', 0, $manualDeductions);
            $this->markLoanInstallmentsPaid($payrollRun);

            $payrollRun->update([
                'approved_by' => auth()->id(),
                'status' => 'approved',
                'posted_at' => now(),
                'journal_entry_id' => $journalEntry->id,
            ]);

            return $journalEntry;
        }, 3);
        $payrollRun->refresh();

        return $journalEntry;
    }

    private function item(JournalEntry $journalEntry, string $code, string $name, string $type, float $debit, float $credit): void
    {
        if (round($debit + $credit, 2) === 0.0) {
            return;
        }

        JournalItem::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => Account::getSystemAccount($code, $name, $type)->id,
            'debit' => round($debit, 2),
            'credit' => round($credit, 2),
        ]);
    }

    private function markLoanInstallmentsPaid(PayrollRun $payrollRun): void
    {
        $payrollRun->employees->each(function ($employeePayroll): void {
            $installmentIds = $employeePayroll->calculation_snapshot['loan_installment_ids'] ?? [];

            if (! is_array($installmentIds) || $installmentIds === []) {
                if ((float) $employeePayroll->loan > 0) {
                    throw new HrWorkflowException('Loan deductions must reference funded loan installments.');
                }

                return;
            }

            $remaining = round((float) $employeePayroll->loan, 2);
            if ($remaining <= 0) {
                return;
            }
            $installments = EmployeeLoanInstallment::query()
                ->whereIn('id', $installmentIds)
                ->whereHas('loan', fn ($query) => $query->where('employee_id', $employeePayroll->employee_id)->whereIn('status', ['active', 'partially_paid'])->where(fn ($funding) => $funding->where('legacy_disbursement_confirmed', true)->orWhereHas('disbursementPayment', fn ($payment) => $payment->whereNull('voided_at'))))
                ->where('status', 'pending')->orderBy('due_date')->orderBy('id')->lockForUpdate()->get();
            $allocations = [];
            foreach ($installments as $installment) {
                $applied = min($remaining, max(0, (float) $installment->amount - (float) $installment->paid_amount));
                if ($applied <= 0) {
                    continue;
                }
                $allocations[$installment->id] = $applied;
                $paid = round((float) $installment->paid_amount + $applied, 2);
                $installment->update([
                    'paid_amount' => $paid,
                    'status' => $paid >= (float) $installment->amount ? 'paid' : 'pending',
                    'payroll_run_employee_id' => $employeePayroll->id,
                    'paid_at' => now(),
                ]);
                $installment->loan->syncStatusFromInstallments();
                $remaining = round($remaining - $applied, 2);
                if ($remaining <= 0) {
                    break;
                }
            }
            $snapshot = $employeePayroll->calculation_snapshot;
            $snapshot['posted_loan_allocations'] = $allocations;
            $employeePayroll->forceFill(['calculation_snapshot' => $snapshot])->saveQuietly();
            if ($remaining > 0) {
                throw new HrWorkflowException('Loan deduction exceeds the selected outstanding installments. Recalculate payroll.');
            }
        });
    }
}

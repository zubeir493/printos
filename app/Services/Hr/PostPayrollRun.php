<?php

namespace App\Services\Hr;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\PayrollRun;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PostPayrollRun
{
    public function handle(PayrollRun $payrollRun): JournalEntry
    {
        if ($payrollRun->status !== 'draft') {
            throw new RuntimeException('Only draft payroll runs can be approved.');
        }

        return DB::transaction(function () use ($payrollRun): JournalEntry {
            $payrollRun->loadMissing('employees');

            if ($payrollRun->employees->isEmpty() || (float) $payrollRun->employees->sum('net_pay') <= 0) {
                throw new RuntimeException('Payroll must have calculated employee net pay before posting.');
            }

            $grossEarning = (float) $payrollRun->employees->sum('gross_earning');
            $netPay = (float) $payrollRun->employees->sum('net_pay');
            $incomeTax = (float) $payrollRun->employees->sum('income_tax');
            $penalty = (float) $payrollRun->employees->sum('penalty_amount');
            $pension = (float) $payrollRun->employees->sum('pension_18');
            $loan = (float) $payrollRun->employees->sum('loan');
            $workersUnion = (float) $payrollRun->employees->sum('workers_union');

            $totalDebit = round($grossEarning, 2);
            $totalCredit = round($netPay + $incomeTax + $penalty + $pension + $loan + $workersUnion, 2);

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

            $this->item($journalEntry, '5100', 'Salary Expense', 'Expense', $grossEarning, 0);
            $this->item($journalEntry, '2150', 'Payroll Payable', 'Liability', 0, $netPay);
            $this->item($journalEntry, '2160', 'PAYE Tax Payable', 'Liability', 0, $incomeTax);
            $this->item($journalEntry, '2165', 'Payroll Penalty Clearing', 'Liability', 0, $penalty);
            $this->item($journalEntry, '2170', 'Pension Payable', 'Liability', 0, $pension);
            $this->item($journalEntry, '2180', 'Loan Payable', 'Liability', 0, $loan);
            $this->item($journalEntry, '2190', 'Workers Union Payable', 'Liability', 0, $workersUnion);

            $payrollRun->update([
                'status' => 'approved',
                'posted_at' => now(),
                'journal_entry_id' => $journalEntry->id,
            ]);

            return $journalEntry;
        });
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
}

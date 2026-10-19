<?php

namespace App\Services\Accounting;

use App\Models\EmployeeLoan;
use App\Models\EmployeeLoanInstallment;
use App\Models\JobOrder;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Payment;
use App\Models\PayrollRun;
use App\Models\PayrollRunEmployee;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class VoidPaymentJournalEntry
{
    public function handle(Payment $payment, string $reason, ?User $user = null): JournalEntry
    {
        return DB::transaction(function () use ($payment, $reason, $user): JournalEntry {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($payment->voided_at) {
                throw new RuntimeException('This payment has already been voided.');
            }

            $originalJournal = $payment->journalEntries()
                ->whereNull('reversal_of_journal_entry_id')
                ->where('status', 'posted')
                ->orderBy('id')
                ->first();

            if (! $originalJournal) {
                throw new RuntimeException('No posted journal entry was found for this payment.');
            }

            $timestamp = now();

            $originalJournal->update([
                'voided_at' => $timestamp,
            ]);

            $reversalJournal = JournalEntry::create([
                'date' => $timestamp->toDateString(),
                'reference' => 'REV-'.$originalJournal->reference,
                'source_type' => Payment::class,
                'source_id' => $payment->id,
                'narration' => 'Reversal of '.$originalJournal->reference.($reason ? ' - '.$reason : ''),
                'total_debit' => $originalJournal->total_debit,
                'total_credit' => $originalJournal->total_credit,
                'status' => 'posted',
                'posted_at' => $timestamp,
                'reversal_of_journal_entry_id' => $originalJournal->id,
            ]);

            foreach ($originalJournal->journalItems as $item) {
                JournalItem::create([
                    'journal_entry_id' => $reversalJournal->id,
                    'account_id' => $item->account_id,
                    'debit' => $item->credit,
                    'credit' => $item->debit,
                ]);
            }

            $payment->update([
                'voided_at' => $timestamp,
                'voided_by' => $user?->id,
                'void_reason' => $reason,
            ]);

            $payment->refresh()->syncRelatedDocumentPaymentState();
            $this->restoreRelatedDocuments($payment);

            if ($payment->payable instanceof JobOrder) {
                $jobOrder = $payment->payable->refresh();
                $jobOrder->updateQuietly([
                    'advance_paid' => $jobOrder->paid_amount > 0,
                    'advance_amount' => $jobOrder->paid_amount,
                ]);
                $jobOrder->syncCompletionStatus();
            }

            if ($payment->bank_id && in_array($payment->method, ['bank', 'bank_transfer', 'cheque', 'check'], true)) {
                $cashAmount = max(0, round((float) $payment->amount - (float) $payment->withholding_amount, 2));

                if ($payment->direction === 'outbound') {
                    $payment->bank()->increment('current_balance', $cashAmount);
                } else {
                    $payment->bank()->decrement('current_balance', $cashAmount);
                }
            }

            return $reversalJournal;
        });
    }

    private function restoreRelatedDocuments(Payment $payment): void
    {
        if ($payment->payable instanceof EmployeeLoan) {
            $loan = EmployeeLoan::query()->lockForUpdate()->findOrFail($payment->payable_id);
            foreach ($payment->loanInstallments()->get() as $allocation) {
                $installment = EmployeeLoanInstallment::query()->lockForUpdate()->findOrFail($allocation->id);
                $paid = max(0, round((float) $installment->paid_amount - (float) $allocation->pivot->amount, 2));
                $installment->update([
                    'paid_amount' => $paid,
                    'status' => $paid >= (float) $installment->amount ? 'paid' : 'pending',
                    'payment_id' => $installment->payment_id === $payment->id ? null : $installment->payment_id,
                    'paid_at' => $paid > 0 ? $installment->paid_at : null,
                ]);
            }
            $loan->syncStatusFromInstallments();
        }

        $runIds = PayrollRunEmployee::query()->where('payment_id', $payment->id)->pluck('payroll_run_id')->unique();
        foreach ($runIds as $runId) {
            $run = PayrollRun::query()->lockForUpdate()->findOrFail($runId);
            $run->employees()->where('payment_id', $payment->id)->update(['payment_id' => null]);
            $run->update(['status' => 'approved', 'paid_at' => null]);
        }
    }
}

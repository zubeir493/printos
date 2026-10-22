<?php

namespace App\Services\Hr;

use App\Exceptions\HrWorkflowException;
use App\Models\EmployeeLoanInstallment;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\PayrollRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CorrectPayrollRun
{
    public function handle(PayrollRun $run, string $reason): PayrollRun
    {
        Gate::authorize('update', $run);

        return DB::transaction(function () use ($run, $reason): PayrollRun {
            $run = PayrollRun::query()->lockForUpdate()->findOrFail($run->id);
            if ($run->status !== 'approved' || blank($reason) || $run->employees()->whereNotNull('payment_id')->exists()
                || $run->paymentBatches()->whereIn('status', ['generated', 'submitted'])->exists()) {
                throw new HrWorkflowException('Reverse settled payments and fail open batches before correcting approved payroll. Supply a reason.');
            }
            if (Payment::query()->where('payable_type', PayrollRun::class)->where('payable_id', $run->id)->whereIn('transaction_type', array_keys(RecordPayrollRemittance::COLUMNS))->whereNull('voided_at')->exists()) {
                throw new HrWorkflowException('Reverse remittances before correcting payroll.');
            }
            $journal = $run->journalEntry()->lockForUpdate()->firstOrFail();
            if ($journal->voided_at) {
                throw new HrWorkflowException('This payroll has already been reversed.');
            }
            $reversal = JournalEntry::create(['date' => now(), 'reference' => 'REV-'.$journal->reference, 'source_type' => PayrollRun::class, 'source_id' => $run->id, 'narration' => 'Payroll correction by '.auth()->id().': '.$reason, 'total_debit' => $journal->total_credit, 'total_credit' => $journal->total_debit, 'status' => 'posted', 'posted_at' => now(), 'reversal_of_journal_entry_id' => $journal->id]);
            foreach ($journal->journalItems as $item) {
                $reversal->journalItems()->create(['account_id' => $item->account_id, 'debit' => $item->credit, 'credit' => $item->debit]);
            }
            $journal->update(['voided_at' => now()]);
            foreach ($run->employees()->get() as $row) {
                $allocations = $row->calculation_snapshot['posted_loan_allocations'] ?? [];
                if ((float) $row->loan > 0 && $allocations === []) {
                    throw new HrWorkflowException('Historical loan allocations require reconciliation before correction.');
                }
                foreach (EmployeeLoanInstallment::query()->whereIn('id', array_keys($allocations))->lockForUpdate()->get() as $installment) {
                    $allocation = $row->calculation_snapshot['posted_loan_allocations'][$installment->id] ?? null;
                    if ($allocation === null) {
                        throw new HrWorkflowException('Historical loan allocations require reconciliation before correction.');
                    }
                    $paid = max(0, round((float) $installment->paid_amount - $allocation, 2));
                    $installment->update(['paid_amount' => $paid, 'status' => $paid >= (float) $installment->amount ? 'paid' : 'pending', 'payroll_run_employee_id' => $installment->payroll_run_employee_id === $row->id ? null : $installment->payroll_run_employee_id, 'paid_at' => $paid > 0 ? $installment->paid_at : null]);
                    $installment->loan->syncStatusFromInstallments();
                }
            }
            $run->update(['status' => 'reversed']);
            $draft = $run->replicate(['status', 'posted_at', 'paid_at', 'approved_by', 'journal_entry_id', 'attendance_reviewed_at', 'attendance_reviewed_by', 'attendance_review_note', 'prepared_at']);
            $draft->fill(['status' => 'draft', 'created_by' => auth()->id(), 'name' => $run->name.' correction of #'.$run->id])->save();
            foreach ($run->employees()->get() as $row) {
                $copy = $row->replicate(['payment_id']);
                $copy->payroll_run_id = $draft->id;
                $copy->saveQuietly();
                foreach ($row->lineItems as $line) {
                    $copy->lineItems()->create($line->only(['type', 'code', 'description', 'amount']));
                }
            }

            return $draft;
        }, 3);
    }
}

<?php

namespace App\Observers;

use App\Models\JobOrder;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Services\InvoiceGeneratorService;

class PaymentAllocationObserver
{
    /**
     * Handle the PaymentAllocation "saving" event.
     * Prevents the total allocated amount from exceeding the payment amount.
     */
    public function saving(PaymentAllocation $paymentAllocation): void
    {
        if (! $paymentAllocation->payment_id) {
            return;
        }

        $payment = Payment::find($paymentAllocation->payment_id);
        if (! $payment) {
            return;
        }

        $existingTotal = (float) PaymentAllocation::where('payment_id', $paymentAllocation->payment_id)
            ->when($paymentAllocation->exists, fn ($q) => $q->where('id', '!=', $paymentAllocation->id))
            ->sum('allocated_amount');

        $newTotal = $existingTotal + (float) $paymentAllocation->allocated_amount;

        if ($newTotal > (float) $payment->amount + 0.001) {
            throw new \RuntimeException(sprintf(
                'Over-allocation: this payment has %.2f Birr available but %.2f Birr would be allocated.',
                (float) $payment->amount - $existingTotal,
                (float) $paymentAllocation->allocated_amount
            ));
        }
    }

    /**
     * Handle the PaymentAllocation "saved" event.
     */
    public function saved(PaymentAllocation $paymentAllocation): void
    {
        $this->updateAllocatable($paymentAllocation);
    }

    /**
     * Handle the PaymentAllocation "updating" event.
     */
    public function updating(PaymentAllocation $paymentAllocation): void
    {
        if ($paymentAllocation->payment_id) {
            $hasJournalEntries = JournalEntry::where('source_type', Payment::class)
                ->where('source_id', $paymentAllocation->payment_id)
                ->exists();

            if ($hasJournalEntries) {
                throw new \RuntimeException('Cannot edit payment allocation after its payment has been posted to the accounting ledger.');
            }
        }
    }

    /**
     * Handle the PaymentAllocation "deleting" event.
     */
    public function deleting(PaymentAllocation $paymentAllocation): void
    {
        if ($paymentAllocation->payment_id) {
            $hasJournalEntries = JournalEntry::where('source_type', Payment::class)
                ->where('source_id', $paymentAllocation->payment_id)
                ->exists();

            if ($hasJournalEntries) {
                throw new \RuntimeException('Cannot delete payment allocation after its payment has been posted to the accounting ledger.');
            }
        }
    }

    /**
     * Handle the PaymentAllocation "deleted" event.
     */
    public function deleted(PaymentAllocation $paymentAllocation): void
    {
        $this->updateAllocatable($paymentAllocation);
    }

    /**
     * Update the parent allocatable based on allocations.
     */
    protected function updateAllocatable(PaymentAllocation $paymentAllocation): void
    {
        $allocatable = $paymentAllocation->allocatable;
        if (! $allocatable) {
            return;
        }

        // Update JobOrder advance payment info.
        // Use the total of all allocations, not just the first one, so that
        // adding/removing allocations always reflects the correct advance amount.
        if ($paymentAllocation->allocatable_type === JobOrder::class) {
            $totalAllocated = (float) $allocatable->paymentAllocations()->sum('allocated_amount');

            $allocatable->updateQuietly([
                'advance_paid' => $totalAllocated > 0,
                'advance_amount' => $totalAllocated,
            ]);

            $allocatable->refresh()->syncCompletionStatus();
        }

        // Synchronize related invoices if they exist
        if (method_exists($allocatable, 'invoices')) {
            $allocatable->invoices()->each(function ($invoice) {
                $invoice->update([
                    'balance_due' => $invoice->order->balance ?? 0,
                    'status' => app(InvoiceGeneratorService::class)->getInvoiceStatus($invoice->order),
                ]);

                // Regenerate the PDF file to reflect the new balance
                app(InvoiceGeneratorService::class)->regeneratePdf($invoice);
            });
        }
    }
}

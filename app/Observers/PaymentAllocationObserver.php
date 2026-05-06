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

        // Update JobOrder advance payment info
        if ($paymentAllocation->allocatable_type === JobOrder::class) {
            $firstAllocation = $allocatable->paymentAllocations()->orderBy('id')->first();

            $allocatable->updateQuietly([
                'advance_paid' => $firstAllocation !== null,
                'advance_amount' => $firstAllocation ? $firstAllocation->allocated_amount : 0,
            ]);
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

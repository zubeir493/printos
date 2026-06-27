<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\JobOrder;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\SalesOrder;

class CreateSalesJournalEntry
{
    public function handle(SalesOrder|JobOrder $sale): void
    {
        $sale = $sale instanceof SalesOrder
            ? ($sale->fresh(['salesOrderItems']) ?? $sale->load('salesOrderItems'))
            : ($sale->fresh() ?? $sale);

        $subtotal = (float) $sale->subtotal;
        $taxAmount = (float) $sale->tax_amount;
        $total = (float) $sale->total;

        if ($total <= 0 && $sale instanceof SalesOrder) {
            $subtotal = $sale->salesOrderItems->sum(fn ($item) => (float) $item->quantity * (float) $item->unit_price);
            $total = $subtotal + $taxAmount;
        }

        if ($total <= 0) {
            return;
        }

        $sourceType = $sale::class;
        $referenceNumber = $sale instanceof SalesOrder ? $sale->order_number : $sale->job_order_number;
        $existingEntry = JournalEntry::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sale->id)
            ->exists();

        if ($existingEntry) {
            return;
        }

        $debitAccount = $sale instanceof SalesOrder && $sale->payment_mode === 'credit'
            ? Account::getSystemAccount(Account::CODE_AR, 'Accounts Receivable', 'Asset')
            : Account::getSystemAccount('1000', 'Cash in Hand', 'Asset');
        $revenueAccount = Account::getSystemAccount('4000', 'Sales Revenue', 'Revenue');
        $taxPayableAccount = Account::getSystemAccount('2100', 'VAT Payable', 'Liability');

        $journalEntry = JournalEntry::create([
            'date' => $sale instanceof SalesOrder ? ($sale->order_date ?? now()) : ($sale->submission_date ?? now()),
            'reference' => 'Sale #'.$referenceNumber,
            'source_type' => $sourceType,
            'source_id' => $sale->id,
            'narration' => 'Sale #'.$referenceNumber,
            'total_debit' => $total,
            'total_credit' => $total,
            'status' => 'posted',
            'posted_at' => now(),
        ]);

        // Debit: Accounts Receivable (full invoice amount)
        JournalItem::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $debitAccount->id,
            'debit' => $total,
            'credit' => 0,
        ]);

        // Credit: Sales Revenue (pre-tax subtotal)
        JournalItem::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $revenueAccount->id,
            'debit' => 0,
            'credit' => $subtotal,
        ]);

        // Credit: VAT Payable (tax portion, only if non-zero)
        if ($taxAmount > 0) {
            JournalItem::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $taxPayableAccount->id,
                'debit' => 0,
                'credit' => $taxAmount,
            ]);
        }
    }
}

<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\SalesOrder;

class CreateSalesJournalEntry
{
    public function handle(SalesOrder $sale): void
    {
        $sale->load('salesOrderItems');

        $subtotal = (float) $sale->subtotal;
        $taxAmount = (float) $sale->tax_amount;
        $total = (float) $sale->total;

        // Fall back to items sum if totals are not yet persisted
        if ($total <= 0) {
            $subtotal = $sale->salesOrderItems->sum(fn ($item) => (float) $item->quantity * (float) $item->unit_price);
            $total = $subtotal + $taxAmount;
        }

        if ($total <= 0) {
            return;
        }

        $existingEntry = JournalEntry::query()
            ->where('source_type', SalesOrder::class)
            ->where('source_id', $sale->id)
            ->exists();

        if ($existingEntry) {
            return;
        }

        $receivablesAccount = Account::getSystemAccount(Account::CODE_AR, 'Accounts Receivable', 'Asset');
        $revenueAccount = Account::getSystemAccount('4000', 'Sales Revenue', 'Revenue');
        $taxPayableAccount = Account::getSystemAccount('2100', 'VAT Payable', 'Liability');

        $journalEntry = JournalEntry::create([
            'date' => $sale->order_date ?? now(),
            'reference' => 'Sale #'.$sale->order_number,
            'source_type' => SalesOrder::class,
            'source_id' => $sale->id,
            'narration' => 'Sale #'.$sale->order_number,
            'total_debit' => $total,
            'total_credit' => $total,
            'status' => 'posted',
            'posted_at' => now(),
        ]);

        // Debit: Accounts Receivable (full invoice amount)
        JournalItem::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $receivablesAccount->id,
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

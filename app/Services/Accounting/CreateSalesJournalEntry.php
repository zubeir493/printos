<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\JobOrder;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use App\Support\PaymentMethod;

class CreateSalesJournalEntry
{
    public function handle(SalesOrder|JobOrder $sale): void
    {
        if ($sale instanceof JobOrder && $sale->production_mode === 'make_to_stock') {
            return;
        }
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

        $costOfGoods = $sale instanceof SalesOrder ? (float) StockMovement::query()
            ->where('reference_type', SalesOrder::class)->where('reference_id', $sale->id)->where('type', 'sale')->sum('total_cost') : 0.0;
        if ($total <= 0 && $costOfGoods <= 0) {
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

        $debitAccount = $sale instanceof JobOrder || ($sale instanceof SalesOrder && $sale->payment_mode === 'credit')
            ? Account::getSystemAccount(Account::CODE_AR, 'Accounts Receivable', 'Asset')
            : $this->resolveImmediateSaleAccount($sale);
        $revenueAccount = Account::getSystemAccount('4000', 'Sales Revenue', 'Revenue');
        $taxPayableAccount = Account::getSystemAccount('2100', 'VAT Payable', 'Liability');

        $journalEntry = JournalEntry::create([
            'date' => $sale instanceof SalesOrder ? ($sale->order_date ?? now()) : ($sale->submission_date ?? now()),
            'reference' => 'Sale #'.$referenceNumber,
            'source_type' => $sourceType,
            'source_id' => $sale->id,
            'narration' => 'Sale #'.$referenceNumber,
            'total_debit' => round($total + $costOfGoods, 2),
            'total_credit' => round($total + $costOfGoods, 2),
            'status' => 'posted',
            'posted_at' => now(),
        ]);

        // Debit the settlement account for the full invoice amount.
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
        if ($costOfGoods > 0) {
            $inventory = Account::getSystemAccount('1500', 'Inventory', 'Asset');
            $cost = Account::getSystemAccount('5000', 'Cost of Goods Sold (COGS)', 'Expense');
            $journalEntry->journalItems()->createMany([
                ['account_id' => $cost->id, 'debit' => $costOfGoods, 'credit' => 0],
                ['account_id' => $inventory->id, 'debit' => 0, 'credit' => $costOfGoods],
            ]);
        }
    }

    private function resolveImmediateSaleAccount(SalesOrder|JobOrder $sale): Account
    {
        if ($sale instanceof SalesOrder && PaymentMethod::requiresBank($sale->payment_method)) {
            return Account::getSystemAccount(Account::CODE_BANK, 'Bank Current Account', 'Asset');
        }

        return Account::getSystemAccount(Account::CODE_CASH, 'Cash in Hand', 'Asset');
    }
}

<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\PurchaseOrder;

class CreatePurchaseOrderJournalEntry
{
    public function handle(PurchaseOrder $purchaseOrder)
    {
        if ($this->hasPostedJournalEntry($purchaseOrder)) {
            return;
        }

        $purchaseOrder->load('purchaseOrderItems');

        $total = (float) $purchaseOrder->total;

        if ($total <= 0) {
            $total = $purchaseOrder->purchaseOrderItems->sum(function ($item) {
                return (float) $item->quantity * (float) $item->unit_price;
            });
        }

        if ($total <= 0) {
            $total = (float) $purchaseOrder->subtotal;
        }

        $inventoryTotal = $purchaseOrder->purchaseOrderItems->sum(function ($item) {
            return (float) $item->quantity * (float) $item->unit_price;
        });

        if ($inventoryTotal <= 0) {
            $inventoryTotal = $total;
        }

        if ($total <= 0) {
            return;
        }

        $inventoryAccount = Account::getSystemAccount('1500', 'Inventory', 'Asset');
        $accountsPayableAccount = Account::getSystemAccount(Account::CODE_AP, 'Accounts Payable', 'Liability');

        $journalEntry = JournalEntry::create([
            'date' => $purchaseOrder->order_date ?? now(),
            'reference' => 'Purchase Order #'.$purchaseOrder->po_number,
            'source_type' => PurchaseOrder::class,
            'source_id' => $purchaseOrder->id,
            'narration' => 'Purchase Order #'.$purchaseOrder->po_number,
            'total_debit' => $total,
            'total_credit' => $total,
            'status' => 'posted',
            'posted_at' => now(),
        ]);

        JournalItem::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $inventoryAccount->id,
            'debit' => $inventoryTotal,
            'credit' => 0,
        ]);

        $taxAmount = round($total - $inventoryTotal, 2);

        if ($taxAmount > 0) {
            JournalItem::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => Account::getSystemAccount('2100', 'VAT Payable', 'Liability')->id,
                'debit' => $taxAmount,
                'credit' => 0,
            ]);
        }

        JournalItem::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $accountsPayableAccount->id,
            'debit' => 0,
            'credit' => $total,
        ]);
    }

    private function hasPostedJournalEntry(PurchaseOrder $purchaseOrder): bool
    {
        return JournalEntry::query()
            ->where('source_type', PurchaseOrder::class)
            ->where('source_id', $purchaseOrder->id)
            ->whereNull('reversal_of_journal_entry_id')
            ->exists();
    }
}

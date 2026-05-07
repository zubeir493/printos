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
        $purchaseOrder->load('purchaseOrderItems');

        $total = $purchaseOrder->purchaseOrderItems->sum(function ($item) {
            return (float) $item->quantity * (float) $item->unit_price;
        });

        if ($total <= 0) {
            $total = (float) $purchaseOrder->subtotal;
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
            'debit' => $total,
            'credit' => 0,
        ]);

        JournalItem::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $accountsPayableAccount->id,
            'debit' => 0,
            'credit' => $total,
        ]);
    }
}

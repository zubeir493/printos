<?php

namespace App\Services;

use App\Enums\PaymentTransactionType;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use App\Services\Accounting\VoidPaymentJournalEntry;
use App\Support\PaymentMethod;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class VoidSalesOrder
{
    public function handle(SalesOrder $sale): void
    {
        DB::transaction(function () use ($sale): void {
            $sale = SalesOrder::query()->lockForUpdate()->findOrFail($sale->id);
            if ($sale->status === SalesOrder::STATUS_VOID) {
                return;
            }
            if (! in_array($sale->status, [SalesOrder::STATUS_DEPOSIT_RECEIVED, SalesOrder::STATUS_SUBMITTED, SalesOrder::STATUS_COMPLETED], true)) {
                throw new RuntimeException('Only active sales orders can be voided.');
            }
            foreach ($sale->payments()->whereNull('voided_at')->lockForUpdate()->get() as $payment) {
                if ($payment->transaction_type !== PaymentTransactionType::CASH_SALE_RECEIPT->value) {
                    app(VoidPaymentJournalEntry::class)->handle($payment, 'Sales order voided', auth()->user());

                    continue;
                }
                $payment->update(['voided_at' => now(), 'voided_by' => auth()->id(), 'void_reason' => 'Sales order voided']);
                if ($payment->bank_id && PaymentMethod::requiresBank($payment->method)) {
                    $payment->bank()->decrement('current_balance', (float) $payment->amount - (float) $payment->withholding_amount);
                }
            }
            $journals = JournalEntry::query()->where('source_type', SalesOrder::class)->where('source_id', $sale->id)
                ->where('status', 'posted')->whereNull('voided_at')->whereNull('reversal_of_journal_entry_id')
                ->with('journalItems')->lockForUpdate()->get();
            foreach ($journals as $original) {
                $reversal = JournalEntry::create([
                    'date' => today(), 'reference' => 'REV-'.$original->reference,
                    'source_type' => SalesOrder::class, 'source_id' => $sale->id,
                    'narration' => 'Sales order voided: '.$sale->order_number,
                    'total_debit' => $original->total_credit, 'total_credit' => $original->total_debit,
                    'status' => 'posted', 'posted_at' => now(), 'reversal_of_journal_entry_id' => $original->id,
                ]);
                foreach ($original->journalItems as $item) {
                    JournalItem::create(['journal_entry_id' => $reversal->id, 'account_id' => $item->account_id, 'debit' => $item->credit, 'credit' => $item->debit]);
                }
                $original->update(['voided_at' => now()]);
            }
            $movements = StockMovement::query()->where('reference_type', SalesOrder::class)->where('reference_id', $sale->id)
                ->whereIn('type', ['sale', 'sale_return'])->get()->groupBy(fn (StockMovement $movement): string => $movement->warehouse_id.':'.$movement->inventory_item_id);
            foreach ($movements as $group) {
                $quantity = -(float) $group->sum('quantity');
                if ($quantity <= 0) {
                    continue;
                }
                StockMovement::create([
                    'inventory_item_id' => $group->first()->inventory_item_id, 'warehouse_id' => $group->first()->warehouse_id,
                    'type' => 'sale_return', 'reference_type' => SalesOrder::class, 'reference_id' => $sale->id,
                    'quantity' => $quantity, 'movement_date' => now(),
                ]);
            }
            $sale->update(['status' => SalesOrder::STATUS_VOID]);
            $sale->invoices()->update(['status' => 'cancelled', 'balance_due' => 0]);
        });
        $sale->refresh();
    }
}

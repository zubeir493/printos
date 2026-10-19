<?php

namespace App\Observers;

use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use App\Notifications\GoodsReceiptPostedNotification;
use App\Services\InventoryService;
use App\UserRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class GoodsReceiptObserver
{
    public function updated(GoodsReceipt $receipt): void
    {
        if (! $receipt->wasChanged('status') || $receipt->status !== 'posted') {
            return;
        }

        $processed = DB::transaction(function () use ($receipt): bool {
            // Re-fetch inside the transaction with a row lock so concurrent
            // requests cannot both pass the posted_at guard simultaneously.
            $locked = GoodsReceipt::with(['items.purchaseOrderItem'])->lockForUpdate()->find($receipt->id);

            if (! $locked || ! is_null($locked->posted_at)) {
                // Already processed by a concurrent request — bail out safely.
                return false;
            }

            if (! $locked->warehouse_id) {
                throw new \Exception("Warehouse ID not selected for Goods Receipt ID: {$locked->id}");
            }

            $purchaseOrder = PurchaseOrder::query()->lockForUpdate()->findOrFail($locked->purchase_order_id);
            if ($purchaseOrder->status !== 'approved') {
                throw new \RuntimeException('Only approved purchase orders can receive stock.');
            }
            if ($locked->items->isEmpty()) {
                throw new \RuntimeException('A receipt must contain at least one item.');
            }
            $received = [];
            foreach ($locked->items as $item) {
                $poItem = PurchaseOrderItem::query()->where('purchase_order_id', $purchaseOrder->id)->lockForUpdate()->find($item->purchase_order_item_id);
                if (! $poItem || $poItem->status === 'cancelled') {
                    throw new \RuntimeException('Every receipt item must belong to this purchase order and be active.');
                }
                $quantity = (float) $item->quantity_received;
                $received[$poItem->id] = ($received[$poItem->id] ?? 0) + $quantity;
                if ($quantity <= 0 || $received[$poItem->id] > round((float) $poItem->quantity - (float) $poItem->received_quantity, 2) + 0.00001) {
                    throw new \RuntimeException('Received quantity must be positive and cannot exceed the remaining ordered quantity.');
                }
            }
            $inventoryService = app(InventoryService::class);

            foreach ($locked->items as $item) {
                $poItem = $item->purchaseOrderItem;

                if (! $poItem) {
                    continue;
                }

                // Re-lock the PO item specifically
                $poItem = PurchaseOrderItem::query()->lockForUpdate()->find($poItem->id);

                $inventoryService->receiveStockInPurchaseUnit(
                    $poItem->inventory_item_id,
                    $locked->warehouse_id,
                    $item->quantity_received,
                    $poItem->unit_price,
                    get_class($locked),
                    $locked->id
                );

                $receivedQuantity = (float) $poItem->received_quantity + (float) $item->quantity_received;

                $poItem->update([
                    'received_quantity' => $receivedQuantity,
                    'status' => match (true) {
                        $receivedQuantity >= (float) $poItem->quantity => 'received',
                        $receivedQuantity > 0 => 'partially_received',
                        default => 'pending',
                    },
                ]);
            }

            // Stamp posted_at last — this is the idempotency sentinel.
            $locked->updateQuietly(['posted_at' => now()]);

            // Auto-mark purchase order as received if all items are fully received
            $purchaseOrder = $locked->purchaseOrder;
            if ($purchaseOrder && $purchaseOrder->status === 'approved') {
                $allItemsFullyReceived = $purchaseOrder->purchaseOrderItems()
                    ->whereRaw('received_quantity >= quantity')
                    ->count() === $purchaseOrder->purchaseOrderItems()->count();

                if ($allItemsFullyReceived) {
                    $purchaseOrder->update(['status' => 'received']);
                }
            }

            return true;
        });

        if (! $processed) {
            return;
        }

        $recipients = User::whereIn('role', [
            UserRole::Admin->value,
            UserRole::Warehouse->value,
            UserRole::Finance->value,
            UserRole::Operations->value,
        ])->get();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new GoodsReceiptPostedNotification($receipt->refresh()));
        }
    }
}

<?php

namespace App\Observers;

use App\Models\InventoryBalance;
use App\Models\JobOrder;
use App\Models\StockMovement;
use App\Notifications\ProductionLoggedNotification;
use App\Notifications\WipReadyForDispatchNotification;
use App\Support\NotificationRecipients;
use App\UserRole;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class StockMovementObserver
{
    public function saving(StockMovement $movement): void
    {
        $this->lockProductionJob($movement);
    }

    public function deleting(StockMovement $movement): void
    {
        $this->lockProductionJob($movement);
    }

    private function lockProductionJob(StockMovement $movement): void
    {
        if ($movement->reference_type === JobOrder::class && $movement->type === 'production_output') {
            JobOrder::query()->whereKey($movement->reference_id)->lockForUpdate()->firstOrFail();
        }
    }

    /**
     * Handle the StockMovement "created" event.
     */
    public function created(StockMovement $stockMovement): void
    {
        Log::info('StockMovementObserver@created', ['id' => $stockMovement->id]);

        $quantityChange = (float) $stockMovement->quantity;
        $this->validateBalanceChange($stockMovement->inventory_item_id, $stockMovement->warehouse_id, $quantityChange);

        $this->applyBalanceChange($stockMovement->inventory_item_id, $stockMovement->warehouse_id, $quantityChange);

        Log::info('Balance updated', [
            'item' => $stockMovement->inventory_item_id,
            'warehouse' => $stockMovement->warehouse_id,
            'change' => $quantityChange,
        ]);

        $this->notifyProductionOutput($stockMovement);
        $this->syncProduction($stockMovement);
    }

    public function updated(StockMovement $stockMovement): void
    {
        Log::info('StockMovementObserver@updated', ['id' => $stockMovement->id]);

        $original = $stockMovement->getOriginal();

        $oldItemId = $original['inventory_item_id'];
        $oldWarehouseId = $original['warehouse_id'];
        $oldQuantity = (float) $original['quantity'];

        $newItemId = $stockMovement->inventory_item_id;
        $newWarehouseId = $stockMovement->warehouse_id;
        $newQuantity = (float) $stockMovement->quantity;

        // Reverse the old change
        $this->applyBalanceChange($oldItemId, $oldWarehouseId, -$oldQuantity);

        // Validate that the new change won't go negative before applying
        $this->validateBalanceChange($newItemId, $newWarehouseId, $newQuantity);

        // Apply the new change
        $this->applyBalanceChange($newItemId, $newWarehouseId, $newQuantity);

        $this->syncProduction($stockMovement);
        if ($stockMovement->wasChanged(['reference_type', 'reference_id', 'type'])) {
            $this->syncProduction($stockMovement, true);
        }

        Log::info('StockMovement updated balance changes applied', [
            'id' => $stockMovement->id,
            'old' => ['item' => $oldItemId, 'warehouse' => $oldWarehouseId, 'qty' => $oldQuantity],
            'new' => ['item' => $newItemId, 'warehouse' => $newWarehouseId, 'qty' => $newQuantity],
        ]);
    }

    public function deleted(StockMovement $stockMovement): void
    {
        $quantityChange = -(float) $stockMovement->quantity;
        $this->validateBalanceChange($stockMovement->inventory_item_id, $stockMovement->warehouse_id, $quantityChange);
        $this->applyBalanceChange($stockMovement->inventory_item_id, $stockMovement->warehouse_id, $quantityChange);
        $this->syncProduction($stockMovement);
    }

    private function syncProduction(StockMovement $movement, bool $original = false): void
    {
        $type = $original ? $movement->getRawOriginal('type') : $movement->type;
        $referenceType = $original ? $movement->getRawOriginal('reference_type') : $movement->reference_type;
        $referenceId = $original ? $movement->getRawOriginal('reference_id') : $movement->reference_id;
        if ($type === 'production_output' && $referenceType === JobOrder::class) {
            JobOrder::find($referenceId)?->syncCompletionStatus();
        }
    }

    private function applyBalanceChange(int $inventoryItemId, int $warehouseId, float $delta): void
    {
        $balance = InventoryBalance::firstOrCreate(
            [
                'inventory_item_id' => $inventoryItemId,
                'warehouse_id' => $warehouseId,
            ],
            ['quantity_on_hand' => 0]
        );

        if ($delta > 0) {
            $balance->increment('quantity_on_hand', $delta);
        } elseif ($delta < 0) {
            $balance->decrement('quantity_on_hand', abs($delta));
        }

        Log::info('Balance change applied (atomic)', [
            'item' => $inventoryItemId,
            'warehouse' => $warehouseId,
            'delta' => $delta,
            'new_qty' => $balance->fresh()->quantity_on_hand,
        ]);
    }

    private function validateBalanceChange(int $inventoryItemId, int $warehouseId, float $delta): void
    {
        $balance = InventoryBalance::query()->where('inventory_item_id', $inventoryItemId)
            ->where('warehouse_id', $warehouseId)->lockForUpdate()->first();

        $currentQty = $balance ? (float) $balance->quantity_on_hand : 0.0;
        $resultingQty = $currentQty + $delta;

        if ($resultingQty < 0) {
            throw new \Exception(sprintf(
                'Cannot apply this stock movement because item %s in warehouse %s would go negative (%s).',
                $inventoryItemId,
                $warehouseId,
                number_format($resultingQty, 2)
            ));
        }
    }

    private function notifyProductionOutput(StockMovement $stockMovement): void
    {
        if ($stockMovement->type !== 'production_output' || $stockMovement->reference_type !== JobOrder::class) {
            return;
        }

        $task = JobOrder::query()->with('partner')->find($stockMovement->reference_id);

        if (! $task) {
            return;
        }

        $recipients = NotificationRecipients::roles(UserRole::Operations, UserRole::Admin);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new ProductionLoggedNotification($task, $stockMovement));
        }

        $balance = InventoryBalance::query()
            ->with(['inventoryItem', 'warehouse'])
            ->where('inventory_item_id', $stockMovement->inventory_item_id)
            ->where('warehouse_id', $stockMovement->warehouse_id)
            ->first();

        if ($balance?->inventoryItem?->type !== 'wip' || (float) $stockMovement->quantity <= 0) {
            return;
        }

        $warehouseUsers = NotificationRecipients::inventoryUsers($balance->warehouse);

        if ($warehouseUsers->isNotEmpty()) {
            Notification::send($warehouseUsers, new WipReadyForDispatchNotification($balance));
        }
    }
}

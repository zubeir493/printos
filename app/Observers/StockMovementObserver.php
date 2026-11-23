<?php

namespace App\Observers;

use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\JobOrder;
use App\Models\StockMovement;
use App\Notifications\ProductionLoggedNotification;
use App\Notifications\WipReadyForDispatchNotification;
use App\Services\JobOrders\JobProductionCosting;
use App\Support\NotificationRecipients;
use App\UserRole;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class StockMovementObserver
{
    public function saving(StockMovement $movement): void
    {
        if (in_array($movement->type, ['purchase', 'production_output', 'sale_return'], true) && $movement->unit_cost !== null) {
            InventoryItem::query()->whereKey($movement->inventory_item_id)->lockForUpdate()->firstOrFail();
        }
        $this->lockProductionJob($movement);
    }

    public function deleting(StockMovement $movement): void
    {
        $this->lockProductionJob($movement, deleting: true);
    }

    private function lockProductionJob(StockMovement $movement, bool $deleting = false): void
    {
        if ($movement->exists) {
            $this->validateProductionMovement(
                $movement->getRawOriginal('reference_type'), $movement->getRawOriginal('reference_id'), $movement->getRawOriginal('type'), true,
            );
        }
        if (! $deleting && (! $movement->exists || $movement->isDirty(['reference_type', 'reference_id', 'type']))) {
            $this->validateProductionMovement($movement->reference_type, $movement->reference_id, $movement->type, $movement->exists);
        }
    }

    private function validateProductionMovement(?string $referenceType, ?int $referenceId, ?string $type, bool $alreadyExists): void
    {
        if ($referenceType !== JobOrder::class || ! in_array($type, ['production_output', 'consumption', 'material_return'], true)) {
            return;
        }
        $job = JobOrder::query()->whereKey($referenceId)->lockForUpdate()->first();
        if (! $job?->requiresCostedStockReceipt()) {
            return;
        }
        if ($type === 'production_output' && ($alreadyExists || ! $job->stock_received_at || $job->productionMovements()->exists())) {
            throw ValidationException::withMessages(['quantity' => 'Finished stock for this run is posted through the reviewed production receipt.']);
        }
        if ($type !== 'production_output' && ($alreadyExists || $job->stock_received_at)) {
            throw ValidationException::withMessages(['quantity' => 'Use a material return to correct an active run’s posted issues. Closed runs have locked material movements.']);
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

        $this->updateInboundAverageCost($stockMovement);

        $this->applyBalanceChange($stockMovement->inventory_item_id, $stockMovement->warehouse_id, $quantityChange);
        if ($stockMovement->reference_type === JobOrder::class && in_array($stockMovement->type, ['consumption', 'material_return'], true)) {
            $job = JobOrder::find($stockMovement->reference_id);
            if ($job?->requiresCostedStockReceipt()) {
                app(JobProductionCosting::class)->postMaterialMovement($job, $stockMovement);
            }
        }

        Log::info('Balance updated', [
            'item' => $stockMovement->inventory_item_id,
            'warehouse' => $stockMovement->warehouse_id,
            'change' => $quantityChange,
        ]);

        $this->notifyProductionOutput($stockMovement);
        $this->syncProduction($stockMovement);
    }

    private function updateInboundAverageCost(StockMovement $movement): void
    {
        if (! in_array($movement->type, ['purchase', 'production_output', 'sale_return'], true) || (float) $movement->quantity <= 0 || $movement->unit_cost === null || (float) $movement->unit_cost < 0) {
            return;
        }
        $item = InventoryItem::query()->lockForUpdate()->findOrFail($movement->inventory_item_id);
        $oldQuantity = InventoryBalance::query()->where('inventory_item_id', $item->id)->orderBy('id')->lockForUpdate()->get()->sum('quantity_on_hand');
        if ((float) $oldQuantity > 0 && (float) $item->average_cost <= 0) {
            if ($movement->type === 'production_output') {
                throw ValidationException::withMessages(['inventory_item_id' => 'Value the existing stock before adding a costed run to this SKU, or use a new SKU.']);
            }

            return;
        }
        $oldValue = BigDecimal::of((string) $oldQuantity)->multipliedBy((string) $item->average_cost);
        $receivedValue = $movement->total_cost !== null && $movement->type !== 'purchase'
            ? BigDecimal::of((string) $movement->total_cost)
            : BigDecimal::of((string) $movement->quantity)->multipliedBy((string) $movement->unit_cost);
        $quantity = BigDecimal::of((string) $oldQuantity)->plus((string) $movement->quantity);
        $item->update(['average_cost' => (string) $oldValue->plus($receivedValue)->dividedBy($quantity, 6, RoundingMode::HALF_UP)]);
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

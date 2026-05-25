<?php

namespace App\Observers;

use App\Models\InventoryBalance;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Log;

class StockMovementObserver
{
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
        $balance = InventoryBalance::firstWhere([
            'inventory_item_id' => $inventoryItemId,
            'warehouse_id' => $warehouseId,
        ]);

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
}

<?php

namespace App\Services;

use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\JobOrder;

class DispatchStockService
{
    public function dispatchIssue(int|string|null $jobOrderId, int|string|null $warehouseId, int|float|string|null $quantity, int|float|string|null $currentQuantity = 0): ?string
    {
        if (! $jobOrderId || ! $warehouseId) {
            return 'Select a job order and warehouse.';
        }
        $job = JobOrder::find($jobOrderId);
        if (! $job || ! in_array((string) $job->status, ['production', 'completed'], true)) {
            return 'Select a job with production output available for dispatch.';
        }
        $qty = (float) $quantity;
        if ($qty < 0 || $qty > $job->remaining_quantity + (float) $currentQuantity) {
            return 'Dispatch quantity exceeds the remaining produced quantity.';
        }
        $item = $this->inventoryItemForJobOrder($job);
        if (! $item) {
            return 'No dispatchable output inventory item was found.';
        }
        if ($qty - (float) $currentQuantity > $this->availableQuantity($item, (int) $warehouseId)) {
            return 'Insufficient output stock in the selected warehouse.';
        }

        return null;
    }

    public function inventoryItemForJobOrder(JobOrder $jobOrder): ?InventoryItem
    {
        return $jobOrder->outputInventoryItem;
    }

    public function availableQuantity(InventoryItem $inventoryItem, int $warehouseId): float
    {
        return (float) (InventoryBalance::where('warehouse_id', $warehouseId)
            ->where('inventory_item_id', $inventoryItem->id)->value('quantity_on_hand') ?? 0);
    }
}

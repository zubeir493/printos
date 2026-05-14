<?php

namespace App\Services;

use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\JobOrderTask;

class DispatchStockService
{
    public function dispatchIssue(int|string|null $taskId, int|string|null $warehouseId, int|float|string|null $quantity, int|float|string|null $currentQuantity = 0): ?string
    {
        $dispatchQuantity = (float) ($quantity ?: 0);
        $alreadyDispatchedQuantity = (float) ($currentQuantity ?: 0);
        $additionalQuantity = $dispatchQuantity - $alreadyDispatchedQuantity;

        if ($additionalQuantity <= 0) {
            return null;
        }

        if (! $taskId || ! $warehouseId) {
            return null;
        }

        $task = JobOrderTask::query()
            ->with('jobOrder')
            ->find($taskId);

        if (! $task) {
            return 'One of the selected dispatch tasks could not be found.';
        }

        $inventoryItem = $this->inventoryItemForTask($task);

        if (! $inventoryItem) {
            return "No dispatchable stock item was found for {$task->name}.";
        }

        $availableQuantity = $this->availableQuantity($inventoryItem, (int) $warehouseId);

        if ($additionalQuantity > $availableQuantity) {
            return sprintf(
                'Only %s %s of %s is available in the selected warehouse',
                number_format($availableQuantity, 2),
                $inventoryItem->unit,
                $inventoryItem->name,
                number_format($additionalQuantity, 2),
            );
        }

        return null;
    }

    public function inventoryItemForTask(JobOrderTask $task): ?InventoryItem
    {
        if ($task->jobOrder->production_mode === 'make_to_order') {
            return InventoryItem::where('sku', 'TASK-'.$task->id)->first();
        }

        return InventoryItem::where('type', 'finished_good')
            ->where('name', 'like', "%{$task->name}%")
            ->first();
    }

    public function availableQuantity(InventoryItem $inventoryItem, int $warehouseId): float
    {
        return (float) (InventoryBalance::where('warehouse_id', $warehouseId)
            ->where('inventory_item_id', $inventoryItem->id)
            ->value('quantity_on_hand') ?? 0);
    }
}

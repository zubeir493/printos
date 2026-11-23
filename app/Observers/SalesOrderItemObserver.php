<?php

namespace App\Observers;

use App\Models\InventoryItem;
use App\Models\SalesOrderItem;

class SalesOrderItemObserver
{
    public function saving(SalesOrderItem $item): void
    {
        $item->total = round((float) $item->quantity * (float) $item->unit_price, 2);
        if (! $item->exists || $item->isDirty('inventory_item_id')) {
            $item->sales_tax_code = InventoryItem::query()->whereKey($item->inventory_item_id)->value('sales_tax_code') ?? 'standard';
        }
    }

    public function saved(SalesOrderItem $item): void
    {
        $item->salesOrder?->recalculateTotals();
    }

    public function deleted(SalesOrderItem $item): void
    {
        $item->salesOrder?->recalculateTotals();
    }
}

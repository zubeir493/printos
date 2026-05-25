<?php

namespace App\Observers;

use App\Models\SalesOrderItem;

class SalesOrderItemObserver
{
    public function saved(SalesOrderItem $item): void
    {
        $item->salesOrder?->recalculateTotals();
    }

    public function deleted(SalesOrderItem $item): void
    {
        $item->salesOrder?->recalculateTotals();
    }
}

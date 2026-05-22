<?php

namespace App\Observers;

use App\Models\SalesOrder;
use App\Services\Accounting\CreateSalesJournalEntry;
use App\Services\SalesOrderPaymentService;

class SalesOrderObserver
{
    public function saved(SalesOrder $salesOrder)
    {
        if (
            $salesOrder->wasChanged('status') &&
            in_array($salesOrder->status, [SalesOrder::STATUS_SUBMITTED, SalesOrder::STATUS_COMPLETED], true)
        ) {
            app(CreateSalesJournalEntry::class)->handle($salesOrder);

            if ($salesOrder->status === SalesOrder::STATUS_COMPLETED) {
                app(SalesOrderPaymentService::class)->createImmediatePaymentForCashSale($salesOrder);
            }
        }
    }
}

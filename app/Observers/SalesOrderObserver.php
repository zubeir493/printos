<?php

namespace App\Observers;

use App\Models\SalesOrder;
use App\Models\User;
use App\Notifications\SalesOrderCreatedNotification;
use App\Notifications\SalesOrderStatusChangedNotification;
use App\Services\Accounting\CreateSalesJournalEntry;
use App\Services\SalesOrderPaymentService;
use App\Support\NotificationRecipients;
use App\UserRole;
use Illuminate\Support\Facades\Notification;

class SalesOrderObserver
{
    public function created(SalesOrder $salesOrder)
    {
        $recipients = User::whereIn('role', [
            UserRole::Admin->value,
            UserRole::Sales->value,
            UserRole::Finance->value,
        ])->get();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new SalesOrderCreatedNotification($salesOrder));
        }
    }

    public function saved(SalesOrder $salesOrder)
    {
        if ($salesOrder->wasChanged('status')) {
            $roles = match ($salesOrder->status) {
                SalesOrder::STATUS_SUBMITTED => [UserRole::Admin, UserRole::Operations, UserRole::Warehouse],
                SalesOrder::STATUS_COMPLETED => [UserRole::Admin, UserRole::Sales, UserRole::Finance, UserRole::Operations],
                SalesOrder::STATUS_VOID => [UserRole::Admin, UserRole::Sales, UserRole::Finance, UserRole::Operations],
                default => [],
            };

            $recipients = $roles === [] ? collect() : NotificationRecipients::roles(...$roles);
            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new SalesOrderStatusChangedNotification($salesOrder));
            }
        }

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

<?php

namespace App\Notifications;

use App\Models\SalesOrder;
use App\Notifications\Concerns\SendsWebPushNotifications;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;

class SalesOrderStatusChangedNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;
    use SendsWebPushNotifications;

    public function __construct(protected SalesOrder $salesOrder) {}

    protected function webPushTitle(): string
    {
        return 'Sales Order '.ucfirst((string) $this->salesOrder->status);
    }

    protected function webPushBody(): string
    {
        return "Sales order {$this->salesOrder->order_number} is now {$this->salesOrder->status}.";
    }

    protected function notificationUrl(object $notifiable): string
    {
        return $this->resourceUrl($notifiable, 'sales-orders', 'view', ['record' => $this->salesOrder]);
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title($this->webPushTitle())
            ->body($this->webPushBody())
            ->icon($this->salesOrder->status === SalesOrder::STATUS_VOID ? 'heroicon-o-x-circle' : 'heroicon-o-arrow-path')
            ->iconColor($this->salesOrder->status === SalesOrder::STATUS_VOID ? 'danger' : 'primary')
            ->actions($this->databaseActions($notifiable, 'Open sales order'))
            ->getDatabaseMessage();
    }
}

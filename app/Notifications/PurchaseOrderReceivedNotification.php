<?php

namespace App\Notifications;

use App\Models\PurchaseOrder;
use App\Notifications\Concerns\SendsWebPushNotifications;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;

class PurchaseOrderReceivedNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;
    use SendsWebPushNotifications;

    public function __construct(protected PurchaseOrder $purchaseOrder) {}

    protected function webPushTitle(): string
    {
        return 'Purchase Order Received';
    }

    protected function webPushBody(): string
    {
        return "Purchase order {$this->purchaseOrder->po_number} has been fully received.";
    }

    protected function notificationUrl(object $notifiable): string
    {
        return $this->resourceUrl($notifiable, 'purchase-orders', 'view', ['record' => $this->purchaseOrder]);
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title($this->webPushTitle())
            ->body($this->webPushBody())
            ->icon('heroicon-o-archive-box')
            ->iconColor('success')
            ->actions($this->databaseActions($notifiable, 'Open purchase order'))
            ->getDatabaseMessage();
    }
}

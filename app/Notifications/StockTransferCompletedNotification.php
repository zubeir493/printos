<?php

namespace App\Notifications;

use App\Models\StockTransfer;
use App\Notifications\Concerns\SendsWebPushNotifications;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;

class StockTransferCompletedNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;
    use SendsWebPushNotifications;

    public function __construct(protected StockTransfer $transfer) {}

    protected function webPushTitle(): string
    {
        return 'Stock Transfer Completed';
    }

    protected function webPushBody(): string
    {
        return "Stock transfer {$this->transfer->transfer_number} is now available at {$this->transfer->toWarehouse->name}.";
    }

    protected function notificationUrl(object $notifiable): string
    {
        return $this->resourceUrl($notifiable, 'stock-transfers', 'edit', ['record' => $this->transfer]);
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title($this->webPushTitle())
            ->body($this->webPushBody())
            ->icon('heroicon-o-arrows-right-left')
            ->iconColor('success')
            ->actions($this->databaseActions($notifiable, 'Open transfer'))
            ->getDatabaseMessage();
    }
}

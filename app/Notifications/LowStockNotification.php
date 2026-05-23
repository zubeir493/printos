<?php

namespace App\Notifications;

use App\Models\InventoryBalance;
use App\Notifications\Concerns\SendsWebPushNotifications;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;

class LowStockNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;
    use SendsWebPushNotifications;

    public function __construct(
        protected InventoryBalance $balance,
        protected bool $isFinished = false
    ) {}

    protected function webPushTitle(): string
    {
        return $this->isFinished ? 'Stock Finished' : 'Low Stock Warning';
    }

    protected function webPushBody(): string
    {
        $item = $this->balance->inventoryItem->name;
        $warehouse = $this->balance->warehouse->name;
        $qty = number_format($this->balance->quantity_on_hand, 2);
        $unit = $this->balance->inventoryItem->unit;

        if ($this->isFinished) {
            return "Stock for '{$item}' in '{$warehouse}' has finished (Current: {$qty} {$unit}).";
        }

        return "Stock for '{$item}' in '{$warehouse}' is low (Current: {$qty} {$unit}).";
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title($this->webPushTitle())
            ->body($this->webPushBody())
            ->icon($this->isFinished ? 'heroicon-o-x-circle' : 'heroicon-o-exclamation-triangle')
            ->iconColor($this->isFinished ? 'danger' : 'warning')
            ->getDatabaseMessage();
    }
}

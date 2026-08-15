<?php

namespace App\Notifications;

use App\Models\InventoryBalance;
use App\Notifications\Concerns\SendsWebPushNotifications;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;

class WipReadyForDispatchNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;
    use SendsWebPushNotifications;

    public function __construct(protected InventoryBalance $balance) {}

    protected function webPushTitle(): string
    {
        return 'WIP Ready for Dispatch';
    }

    protected function webPushBody(): string
    {
        $item = $this->balance->inventoryItem;
        $warehouse = $this->balance->warehouse;

        return "{$item->name} now has ".number_format((float) $this->balance->quantity_on_hand, 2)
            ." {$item->unit} available in {$warehouse->name}.";
    }

    protected function notificationUrl(object $notifiable): string
    {
        return $this->resourceUrl($notifiable, 'inventory-items', 'view', [
            'record' => $this->balance->inventory_item_id,
        ]);
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title($this->webPushTitle())
            ->body($this->webPushBody())
            ->icon('heroicon-o-truck')
            ->iconColor('success')
            ->actions($this->databaseActions($notifiable, 'Open item'))
            ->getDatabaseMessage();
    }
}

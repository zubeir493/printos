<?php

namespace App\Observers;

use App\Models\InventoryBalance;
use App\Notifications\LowStockNotification;
use App\Support\NotificationRecipients;
use App\UserRole;
use Illuminate\Support\Facades\Notification;

class InventoryBalanceObserver
{
    /**
     * Handle the InventoryBalance "updated" event.
     */
    public function updated(InventoryBalance $balance): void
    {
        $this->checkAndNotify($balance);
    }

    /**
     * Handle the InventoryBalance "created" event.
     */
    public function created(InventoryBalance $balance): void
    {
        $this->checkAndNotify($balance);
    }

    protected function checkAndNotify(InventoryBalance $balance): void
    {
        $item = $balance->inventoryItem;
        $newQty = (float) $balance->quantity_on_hand;
        $oldQty = (float) $balance->getOriginal('quantity_on_hand', 0);

        $threshold = (float) ($item->low_stock_threshold ?? 0);

        // Notify if stock finished (crossed 0 or reached 0 from above)
        if ($newQty <= 0 && $oldQty > 0) {
            $this->notifyWarehouseUsers($balance, true);

            return; // Don't send both low and finished
        }

        // Notify if stock low (crossed threshold from above)
        if ($threshold > 0 && $newQty <= $threshold && $oldQty > $threshold) {
            $this->notifyWarehouseUsers($balance, false);
        }
    }

    protected function notifyWarehouseUsers(InventoryBalance $balance, bool $isFinished): void
    {
        $users = NotificationRecipients::inventoryUsers($balance->warehouse)
            ->merge(NotificationRecipients::roles(UserRole::Admin, UserRole::Operations))
            ->unique('id')
            ->values();

        if ($users->isNotEmpty()) {
            Notification::send($users, new LowStockNotification($balance, $isFinished));
        }
    }
}

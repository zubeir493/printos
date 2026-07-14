<?php

namespace App\Observers;

use App\Models\Dispatch;
use App\Notifications\DispatchCreatedNotification;
use App\Support\NotificationRecipients;
use App\UserRole;
use Illuminate\Support\Facades\Notification;

class DispatchObserver
{
    public function created(Dispatch $dispatch): void
    {
        $recipients = NotificationRecipients::roles(UserRole::Warehouse, UserRole::Operations);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new DispatchCreatedNotification($dispatch));
        }
    }
}

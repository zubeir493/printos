<?php

namespace App\Observers;

use App\Models\Dispatch;
use App\Notifications\DispatchCreatedNotification;
use App\Notifications\DispatchStatusChangedNotification;
use App\Support\NotificationRecipients;
use App\UserRole;
use Illuminate\Support\Facades\Notification;

class DispatchObserver
{
    public function created(Dispatch $dispatch): void
    {
        $recipients = NotificationRecipients::roles(UserRole::Warehouse, UserRole::Operations, UserRole::Sales);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new DispatchCreatedNotification($dispatch));
        }
    }

    public function updated(Dispatch $dispatch): void
    {
        if (! $dispatch->wasChanged('status') || ! in_array($dispatch->status, ['completed', 'delivered', 'cancelled'], true)) {
            return;
        }

        $recipients = NotificationRecipients::roles(
            UserRole::Admin,
            UserRole::Sales,
            UserRole::Operations,
            UserRole::Warehouse,
        );

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new DispatchStatusChangedNotification($dispatch->loadMissing('jobOrder')));
        }
    }
}

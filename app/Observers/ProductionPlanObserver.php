<?php

namespace App\Observers;

use App\Models\ProductionPlan;
use App\Notifications\ProductionPlanApprovedNotification;
use App\Support\NotificationRecipients;
use App\UserRole;
use Illuminate\Support\Facades\Notification;

class ProductionPlanObserver
{
    public function updated(ProductionPlan $productionPlan): void
    {
        if (! $productionPlan->wasChanged('status') || $productionPlan->status !== 'approved') {
            return;
        }

        $recipients = NotificationRecipients::roles(UserRole::Production);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new ProductionPlanApprovedNotification($productionPlan));
        }
    }
}

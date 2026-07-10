<?php

namespace App\Observers;

use App\Models\ProductionReport;
use App\Notifications\ProductionReportGeneratedNotification;
use App\Notifications\ProductionReportSubmittedNotification;
use App\Support\NotificationRecipients;
use App\UserRole;
use Illuminate\Support\Facades\Notification;

class ProductionReportObserver
{
    public function created(ProductionReport $productionReport): void
    {
        $recipients = NotificationRecipients::roles(UserRole::Operations, UserRole::Admin);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new ProductionReportGeneratedNotification($productionReport));
        }
    }

    public function updated(ProductionReport $productionReport): void
    {
        if (! $productionReport->wasChanged('status') || $productionReport->status !== 'submitted') {
            return;
        }

        $recipients = NotificationRecipients::roles(UserRole::Operations, UserRole::Admin);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new ProductionReportSubmittedNotification($productionReport));
        }
    }
}

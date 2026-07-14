<?php

namespace App\Notifications;

use App\Models\ProductionReport;
use App\Notifications\Concerns\SendsWebPushNotifications;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;

class ProductionReportSubmittedNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;
    use SendsWebPushNotifications;

    public function __construct(protected ProductionReport $productionReport) {}

    protected function webPushTitle(): string
    {
        return 'Production Report Submitted';
    }

    protected function webPushBody(): string
    {
        $plan = $this->productionReport->productionPlan;

        return 'Production report for '
            .$plan->week_start->format('M d')
            .' - '
            .$plan->week_end->format('M d')
            .' has been submitted.';
    }

    protected function notificationUrl(object $notifiable): string
    {
        return $this->resourceUrl($notifiable, 'production-reports', 'view', ['record' => $this->productionReport]);
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title($this->webPushTitle())
            ->body($this->webPushBody())
            ->icon('heroicon-o-check-circle')
            ->iconColor('success')
            ->actions($this->databaseActions($notifiable, 'Open report'))
            ->getDatabaseMessage();
    }
}

<?php

namespace App\Notifications;

use App\Models\ProductionReport;
use App\Notifications\Concerns\SendsWebPushNotifications;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;

class ProductionReportGeneratedNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;
    use SendsWebPushNotifications;

    public function __construct(protected ProductionReport $productionReport) {}

    protected function webPushTitle(): string
    {
        return 'Production Report Generated';
    }

    protected function webPushBody(): string
    {
        $plan = $this->productionReport->productionPlan;

        return 'A production report was generated for '
            .$plan->week_start->format('M d')
            .' - '
            .$plan->week_end->format('M d')
            .'.';
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
            ->icon('heroicon-o-clipboard-document-check')
            ->iconColor('primary')
            ->actions($this->databaseActions($notifiable, 'Open report'))
            ->getDatabaseMessage();
    }
}

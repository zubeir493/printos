<?php

namespace App\Notifications;

use App\Models\ProductionPlan;
use App\Notifications\Concerns\SendsWebPushNotifications;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;

class ProductionPlanApprovedNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;
    use SendsWebPushNotifications;

    public function __construct(protected ProductionPlan $productionPlan) {}

    protected function webPushTitle(): string
    {
        return 'Production Plan Approved';
    }

    protected function webPushBody(): string
    {
        return 'Production plan for '
            .$this->productionPlan->week_start->format('M d')
            .' - '
            .$this->productionPlan->week_end->format('M d')
            .' has been approved.';
    }

    protected function notificationUrl(object $notifiable): string
    {
        return $this->resourceUrl($notifiable, 'production-plans', 'view', ['record' => $this->productionPlan]);
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title($this->webPushTitle())
            ->body($this->webPushBody())
            ->icon('heroicon-o-document-check')
            ->iconColor('success')
            ->actions($this->databaseActions($notifiable, 'Open plan'))
            ->getDatabaseMessage();
    }
}

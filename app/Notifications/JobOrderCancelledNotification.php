<?php

namespace App\Notifications;

use App\Models\JobOrder;
use App\Notifications\Concerns\SendsWebPushNotifications;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;

class JobOrderCancelledNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;
    use SendsWebPushNotifications;

    public function __construct(protected JobOrder $jobOrder) {}

    protected function webPushTitle(): string
    {
        return 'Job Order Cancelled';
    }

    protected function webPushBody(): string
    {
        return "Job order {$this->jobOrder->job_order_number} has been cancelled.";
    }

    protected function notificationUrl(object $notifiable): string
    {
        return $this->resourceUrl($notifiable, 'job-orders', 'view', ['record' => $this->jobOrder]);
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title($this->webPushTitle())
            ->body($this->webPushBody())
            ->icon('heroicon-o-x-circle')
            ->iconColor('danger')
            ->actions($this->databaseActions($notifiable, 'Open job order'))
            ->getDatabaseMessage();
    }
}

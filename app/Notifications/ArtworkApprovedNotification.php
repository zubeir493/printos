<?php

namespace App\Notifications;

use App\Models\Artwork;
use App\Notifications\Concerns\SendsWebPushNotifications;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;

class ArtworkApprovedNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;
    use SendsWebPushNotifications;

    public function __construct(protected Artwork $artwork) {}

    protected function webPushTitle(): string
    {
        return 'Artwork Approved';
    }

    protected function webPushBody(): string
    {
        return "Your artwork for task '{$this->artwork->jobOrderTask->name}' on job {$this->artwork->jobOrder->job_order_number} has been approved.";
    }

    protected function notificationUrl(object $notifiable): string
    {
        return $this->resourceUrl($notifiable, 'job-order-tasks', 'view', ['record' => $this->artwork->jobOrderTask]);
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title($this->webPushTitle())
            ->body($this->webPushBody())
            ->icon('heroicon-o-check-badge')
            ->iconColor('success')
            ->actions($this->databaseActions($notifiable, 'Open task'))
            ->getDatabaseMessage();
    }
}

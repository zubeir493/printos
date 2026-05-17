<?php

namespace App\Notifications;

use App\Models\Artwork;
use App\Notifications\Concerns\SendsWebPushNotifications;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;

class ArtworkUploadedNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;
    use SendsWebPushNotifications;

    public function __construct(protected Artwork $artwork) {}

    protected function webPushTitle(): string
    {
        return 'New Artwork Uploaded';
    }

    protected function webPushBody(): string
    {
        return "New artwork has been uploaded for task '{$this->artwork->jobOrderTask->name}' on job {$this->artwork->jobOrder->job_order_number}. Please review and approve it.";
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title($this->webPushTitle())
            ->body($this->webPushBody())
            ->icon('heroicon-o-photo')
            ->iconColor('primary')
            ->getDatabaseMessage();
    }
}

<?php

namespace App\Notifications;

use App\Models\Artwork;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

class ArtworkUploadedNotification extends Notification
{
    public function __construct(protected Artwork $artwork) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('New Artwork Uploaded')
            ->body("New artwork has been uploaded for task '{$this->artwork->jobOrderTask->name}' on job {$this->artwork->jobOrder->job_order_number}.")
            ->icon('heroicon-o-photo')
            ->iconColor('warning')
            ->getDatabaseMessage();
    }
}

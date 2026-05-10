<?php

namespace App\Notifications;

use App\Models\Artwork;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

class ArtworkApprovedNotification extends Notification
{
    public function __construct(protected Artwork $artwork) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Artwork Approved')
            ->body("Your artwork for task '{$this->artwork->jobOrderTask->name}' on job {$this->artwork->jobOrder->job_order_number} has been approved.")
            ->icon('heroicon-o-check-badge')
            ->iconColor('success')
            ->getDatabaseMessage();
    }
}

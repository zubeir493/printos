<?php

namespace App\Notifications;

use App\Models\Artwork;
use Illuminate\Notifications\Notification;

class ArtworkUploadedNotification extends Notification
{
    public function __construct(protected Artwork $artwork)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'artwork_uploaded',
            'title' => 'New Artwork Uploaded',
            'message' => "New artwork has been uploaded for task '{$this->artwork->jobOrderTask->name}' on job {$this->artwork->jobOrder->job_order_number}.",
            'artwork_id' => $this->artwork->id,
            'job_order_task_id' => $this->artwork->job_order_task_id,
            'job_order_id' => $this->artwork->jobOrder->id,
        ];
    }
}

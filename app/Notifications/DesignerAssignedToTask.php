<?php

namespace App\Notifications;

use App\Models\JobOrderTask;
use Illuminate\Notifications\Notification;

class DesignerAssignedToTask extends Notification
{
    public function __construct(protected JobOrderTask $task)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'designer_assigned',
            'title' => 'Design Task Assigned',
            'message' => "You have been assigned to task '{$this->task->name}' for job {$this->task->jobOrder->job_order_number}.",
            'task_id' => $this->task->id,
            'job_order_id' => $this->task->job_order_id,
        ];
    }
}

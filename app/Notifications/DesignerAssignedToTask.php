<?php

namespace App\Notifications;

use App\Models\JobOrderTask;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

class DesignerAssignedToTask extends Notification
{
    public function __construct(protected JobOrderTask $task) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $body = "You have been assigned to task '{$this->task->name}' for job {$this->task->jobOrder->job_order_number}.\n";

        if (filled($this->task->instructions)) {
            $body .= "\n\nBrief: {$this->task->instructions}";
        }

        return FilamentNotification::make()
            ->title('Design Task Assigned')
            ->body($body)
            ->icon('heroicon-o-paint-brush')
            ->iconColor('primary')
            ->getDatabaseMessage();
    }
}

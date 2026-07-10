<?php

namespace App\Notifications;

use App\Models\JobOrderTask;
use App\Notifications\Concerns\SendsWebPushNotifications;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;

class DesignerAssignedToTask extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;
    use SendsWebPushNotifications;

    public function __construct(protected JobOrderTask $task) {}

    protected function webPushTitle(): string
    {
        return 'Design Task Assigned';
    }

    protected function webPushBody(): string
    {
        $body = "You have been assigned to task '{$this->task->name}' for job {$this->task->jobOrder->job_order_number}.\n";

        if (filled($this->task->instructions)) {
            $body .= "\n\nBrief: {$this->task->instructions}";
        }

        return $body;
    }

    protected function notificationUrl(object $notifiable): string
    {
        return $this->resourceUrl($notifiable, 'job-order-tasks', 'view', ['record' => $this->task]);
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title($this->webPushTitle())
            ->body($this->webPushBody())
            ->icon('heroicon-o-paint-brush')
            ->iconColor('primary')
            ->actions($this->databaseActions($notifiable, 'Open task'))
            ->getDatabaseMessage();
    }
}

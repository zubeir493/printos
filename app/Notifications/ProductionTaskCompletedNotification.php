<?php

namespace App\Notifications;

use App\Models\JobOrderTask;
use App\Notifications\Concerns\SendsWebPushNotifications;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;

class ProductionTaskCompletedNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;
    use SendsWebPushNotifications;

    public function __construct(protected JobOrderTask $task) {}

    protected function webPushTitle(): string
    {
        return 'Production Task Completed';
    }

    protected function webPushBody(): string
    {
        return "Task '{$this->task->name}' on job {$this->task->jobOrder->job_order_number} has been completed.";
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
            ->icon('heroicon-o-check-circle')
            ->iconColor('success')
            ->actions($this->databaseActions($notifiable, 'Open task'))
            ->getDatabaseMessage();
    }
}

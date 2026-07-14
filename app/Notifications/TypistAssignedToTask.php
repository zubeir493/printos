<?php

namespace App\Notifications;

use App\Models\JobOrderTask;
use App\Notifications\Concerns\SendsWebPushNotifications;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Support\Colors\Color;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;

class TypistAssignedToTask extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;
    use SendsWebPushNotifications;

    public function __construct(protected JobOrderTask $task) {}

    protected function webPushTitle(): string
    {
        return 'Typist Task Assigned';
    }

    protected function webPushBody(): string
    {
        $body = "You have been assigned to type task '{$this->task->name}' for job {$this->task->jobOrder->job_order_number}.";

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
            ->icon('heroicon-o-document-text')
            ->iconColor(Color::Indigo)
            ->actions($this->databaseActions($notifiable, 'Open task'))
            ->getDatabaseMessage();
    }
}

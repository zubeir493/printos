<?php

namespace App\Notifications;

use App\Models\MaterialRequest;
use App\Notifications\Concerns\SendsWebPushNotifications;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;

class MaterialRequestCreatedNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;
    use SendsWebPushNotifications;

    public function __construct(protected MaterialRequest $materialRequest) {}

    protected function webPushTitle(): string
    {
        return 'New Material Request';
    }

    protected function webPushBody(): string
    {
        $task = $this->materialRequest->jobOrderTask;
        $jobOrder = $task->jobOrder;

        return "Task '{$task->name}' on job {$jobOrder->job_order_number} requires {$this->materialRequest->requested_quantity} of {$this->materialRequest->inventoryItem->name}.";
    }

    protected function notificationUrl(object $notifiable): string
    {
        return $this->resourceUrl($notifiable, 'material-requests', 'index');
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title($this->webPushTitle())
            ->body($this->webPushBody())
            ->icon('heroicon-o-archive-box-arrow-down')
            ->iconColor('primary')
            ->actions($this->databaseActions($notifiable, 'Open requests'))
            ->getDatabaseMessage();
    }
}

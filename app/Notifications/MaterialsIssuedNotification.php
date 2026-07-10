<?php

namespace App\Notifications;

use App\Models\MaterialRequest;
use App\Notifications\Concerns\SendsWebPushNotifications;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;

class MaterialsIssuedNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;
    use SendsWebPushNotifications;

    public function __construct(
        protected MaterialRequest $materialRequest,
        protected float $quantity,
    ) {}

    protected function webPushTitle(): string
    {
        return 'Materials Issued';
    }

    protected function webPushBody(): string
    {
        $task = $this->materialRequest->jobOrderTask;

        return number_format($this->quantity, 2)
            ." of {$this->materialRequest->inventoryItem->name} was issued for task '{$task->name}' on job {$task->jobOrder->job_order_number}.";
    }

    protected function notificationUrl(object $notifiable): string
    {
        return $this->resourceUrl($notifiable, 'job-order-tasks', 'view', ['record' => $this->materialRequest->jobOrderTask]);
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title($this->webPushTitle())
            ->body($this->webPushBody())
            ->icon('heroicon-o-archive-box-arrow-down')
            ->iconColor('success')
            ->actions($this->databaseActions($notifiable, 'Open task'))
            ->getDatabaseMessage();
    }
}

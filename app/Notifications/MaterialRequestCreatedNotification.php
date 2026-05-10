<?php

namespace App\Notifications;

use App\Models\MaterialRequest;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

class MaterialRequestCreatedNotification extends Notification
{
    public function __construct(protected MaterialRequest $materialRequest) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $task = $this->materialRequest->jobOrderTask;
        $jobOrder = $task->jobOrder;

        return FilamentNotification::make()
            ->title('New Material Request')
            ->body("Task '{$task->name}' on job {$jobOrder->job_order_number} requires {$this->materialRequest->requested_quantity} of {$this->materialRequest->inventoryItem->name}.")
            ->icon('heroicon-o-archive-box-arrow-down')
            ->iconColor('warning')
            ->getDatabaseMessage();
    }
}

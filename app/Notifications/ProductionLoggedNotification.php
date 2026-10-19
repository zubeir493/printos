<?php

namespace App\Notifications;

use App\Models\JobOrder;
use App\Models\StockMovement;
use App\Notifications\Concerns\SendsWebPushNotifications;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;

class ProductionLoggedNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;
    use SendsWebPushNotifications;

    public function __construct(
        protected JobOrder $task,
        protected StockMovement $stockMovement,
    ) {}

    protected function webPushTitle(): string
    {
        return 'Production Logged';
    }

    protected function webPushBody(): string
    {
        return number_format((float) $this->stockMovement->quantity, 2)
            ." units were produced for task '{$this->task->name}' on job {$this->task->job_order_number}.";
    }

    protected function notificationUrl(object $notifiable): string
    {
        return $this->resourceUrl($notifiable, 'job-orders', 'view', ['record' => $this->task]);
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

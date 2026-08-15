<?php

namespace App\Notifications;

use App\Models\Dispatch;
use App\Notifications\Concerns\SendsWebPushNotifications;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;

class DispatchStatusChangedNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;
    use SendsWebPushNotifications;

    public function __construct(protected Dispatch $dispatch) {}

    protected function webPushTitle(): string
    {
        return 'Dispatch '.ucfirst((string) $this->dispatch->status);
    }

    protected function webPushBody(): string
    {
        return "Dispatch for job {$this->dispatch->jobOrder->job_order_number} is now {$this->dispatch->status}.";
    }

    protected function notificationUrl(object $notifiable): string
    {
        return $this->resourceUrl($notifiable, 'dispatches', 'view', ['record' => $this->dispatch]);
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title($this->webPushTitle())
            ->body($this->webPushBody())
            ->icon($this->dispatch->status === 'cancelled' ? 'heroicon-o-x-circle' : 'heroicon-o-truck')
            ->iconColor($this->dispatch->status === 'cancelled' ? 'danger' : 'success')
            ->actions($this->databaseActions($notifiable, 'Open dispatch'))
            ->getDatabaseMessage();
    }
}

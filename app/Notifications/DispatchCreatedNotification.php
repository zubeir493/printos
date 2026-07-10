<?php

namespace App\Notifications;

use App\Models\Dispatch;
use App\Notifications\Concerns\SendsWebPushNotifications;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;

class DispatchCreatedNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;
    use SendsWebPushNotifications;

    public function __construct(protected Dispatch $dispatch) {}

    protected function webPushTitle(): string
    {
        return 'Dispatch Created';
    }

    protected function webPushBody(): string
    {
        return "Dispatch for job {$this->dispatch->jobOrder->job_order_number} is ready for warehouse processing.";
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
            ->icon('heroicon-o-truck')
            ->iconColor('primary')
            ->actions($this->databaseActions($notifiable, 'Open dispatch'))
            ->getDatabaseMessage();
    }
}

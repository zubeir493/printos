<?php

namespace App\Notifications;

use App\Models\AccountingExport;
use App\Notifications\Concerns\SendsWebPushNotifications;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

class AccountingExportFailed extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;
    use SendsWebPushNotifications;

    public function __construct(public AccountingExport $export) {}

    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->danger()
            ->title($this->webPushTitle())
            ->body($this->webPushBody())
            ->icon('heroicon-o-exclamation-triangle')
            ->iconColor('danger')
            ->actions($this->databaseActions($notifiable, 'Open export'))
            ->getDatabaseMessage();
    }

    protected function webPushTitle(): string
    {
        return 'Peachtree Export Failed';
    }

    protected function webPushBody(): string
    {
        return $this->export->error_message ?? 'Review the failed export for details.';
    }

    protected function notificationUrl(object $notifiable): string
    {
        return $this->resourceUrl($notifiable, 'accounting-exports', 'view', ['record' => $this->export]);
    }
}

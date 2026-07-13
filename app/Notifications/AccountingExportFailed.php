<?php

namespace App\Notifications;

use App\Models\AccountingExport;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AccountingExportFailed extends Notification
{
    use Queueable;

    public function __construct(public AccountingExport $export) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->danger()
            ->title('Peachtree export failed')
            ->body($this->export->error_message ?? 'Review the failed export for details.')
            ->getDatabaseMessage();
    }
}

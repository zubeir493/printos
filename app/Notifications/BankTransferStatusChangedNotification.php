<?php

namespace App\Notifications;

use App\Models\BankTransfer;
use App\Notifications\Concerns\SendsWebPushNotifications;
use App\Support\Money;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;

class BankTransferStatusChangedNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;
    use SendsWebPushNotifications;

    public function __construct(protected BankTransfer $bankTransfer) {}

    protected function webPushTitle(): string
    {
        return 'Bank Transfer '.ucfirst($this->bankTransfer->status);
    }

    protected function webPushBody(): string
    {
        return "Transfer {$this->bankTransfer->transfer_number} from {$this->bankTransfer->fromBank?->name} to {$this->bankTransfer->toBank?->name} is {$this->bankTransfer->status}. Amount: ".Money::format($this->bankTransfer->amount, 2).'.';
    }

    protected function notificationUrl(object $notifiable): string
    {
        return $this->resourceUrl($notifiable, 'bank-transfers', 'edit', ['record' => $this->bankTransfer]);
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title($this->webPushTitle())
            ->body($this->webPushBody())
            ->icon($this->bankTransfer->status === 'cancelled' ? 'heroicon-o-x-circle' : 'heroicon-o-arrows-right-left')
            ->iconColor($this->bankTransfer->status === 'cancelled' ? 'danger' : 'success')
            ->actions($this->databaseActions($notifiable, 'Open transfer'))
            ->getDatabaseMessage();
    }
}

<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Notifications\Concerns\SendsWebPushNotifications;
use App\Support\Money;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;

class PaymentVoidedNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;
    use SendsWebPushNotifications;

    public function __construct(protected Payment $payment) {}

    protected function webPushTitle(): string
    {
        return 'Payment Voided';
    }

    protected function webPushBody(): string
    {
        return "Payment {$this->payment->payment_number} was voided. Amount: ".Money::format($this->payment->amount, 2).'.';
    }

    protected function notificationUrl(object $notifiable): string
    {
        return $this->resourceUrl($notifiable, 'payments', 'view', ['record' => $this->payment]);
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title($this->webPushTitle())
            ->body($this->webPushBody())
            ->icon('heroicon-o-no-symbol')
            ->iconColor('danger')
            ->actions($this->databaseActions($notifiable, 'Open payment'))
            ->getDatabaseMessage();
    }
}

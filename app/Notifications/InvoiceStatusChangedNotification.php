<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Notifications\Concerns\SendsWebPushNotifications;
use App\Support\Money;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;

class InvoiceStatusChangedNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;
    use SendsWebPushNotifications;

    public function __construct(protected Invoice $invoice) {}

    protected function webPushTitle(): string
    {
        return 'Invoice '.ucfirst($this->invoice->status);
    }

    protected function webPushBody(): string
    {
        return "Invoice {$this->invoice->invoice_number} is now {$this->invoice->status}. Balance due: ".Money::format($this->invoice->balance_due, 2).'.';
    }

    protected function notificationUrl(object $notifiable): string
    {
        return $this->resourceUrl($notifiable, 'invoices', 'view', ['record' => $this->invoice]);
    }

    public function toDatabase(object $notifiable): array
    {
        $danger = in_array($this->invoice->status, ['cancelled', 'overdue'], true);

        return FilamentNotification::make()
            ->title($this->webPushTitle())
            ->body($this->webPushBody())
            ->icon($danger ? 'heroicon-o-exclamation-circle' : 'heroicon-o-document-check')
            ->iconColor($danger ? 'danger' : 'success')
            ->actions($this->databaseActions($notifiable, 'Open invoice'))
            ->getDatabaseMessage();
    }
}

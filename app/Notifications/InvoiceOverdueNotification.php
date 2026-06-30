<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Notifications\Concerns\SendsWebPushNotifications;
use App\Support\Money;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;

class InvoiceOverdueNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;
    use SendsWebPushNotifications;

    public function __construct(protected Invoice $invoice) {}

    protected function webPushTitle(): string
    {
        return 'Invoice Overdue';
    }

    protected function webPushBody(): string
    {
        $partner = $this->invoice->partner?->name ?? 'Unknown';
        $overdueDays = now()->diffInDays($this->invoice->due_date);

        return "Invoice {$this->invoice->invoice_number} for {$partner} is {$overdueDays} day(s) overdue. Balance due: ".Money::format($this->invoice->balance_due, 2).'.';
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title($this->webPushTitle())
            ->body($this->webPushBody())
            ->icon('heroicon-o-exclamation-circle')
            ->iconColor('danger')
            ->getDatabaseMessage();
    }
}

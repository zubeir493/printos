<?php

namespace App\Notifications;

use App\Models\Invoice;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

class InvoiceOverdueNotification extends Notification
{
    public function __construct(protected Invoice $invoice) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $partner = $this->invoice->partner?->name ?? 'Unknown';
        $overdueDays = now()->diffInDays($this->invoice->due_date);

        return FilamentNotification::make()
            ->title('Invoice Overdue')
            ->body("Invoice {$this->invoice->invoice_number} for {$partner} is {$overdueDays} day(s) overdue. Balance due: ".number_format($this->invoice->balance_due, 2).' Birr.')
            ->icon('heroicon-o-exclamation-circle')
            ->iconColor('danger')
            ->getDatabaseMessage();
    }
}

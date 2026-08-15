<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Notifications\InvoiceStatusChangedNotification;
use App\Support\NotificationRecipients;
use App\UserRole;
use Illuminate\Support\Facades\Notification;

class InvoiceObserver
{
    public function created(Invoice $invoice): void
    {
        if ($invoice->status === 'sent') {
            $this->notifyTeams($invoice);
        }
    }

    public function updated(Invoice $invoice): void
    {
        if (! $invoice->wasChanged('status') || ! in_array($invoice->status, ['sent', 'paid', 'cancelled'], true)) {
            return;
        }

        $this->notifyTeams($invoice);
    }

    private function notifyTeams(Invoice $invoice): void
    {
        $recipients = NotificationRecipients::roles(UserRole::Admin, UserRole::Finance, UserRole::Sales, UserRole::Operations);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new InvoiceStatusChangedNotification($invoice));
        }
    }
}

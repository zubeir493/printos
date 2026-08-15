<?php

namespace App\Observers;

use App\Models\PurchaseOrder;
use App\Notifications\PurchaseOrderApprovedNotification;
use App\Notifications\PurchaseOrderCancelledNotification;
use App\Notifications\PurchaseOrderReceivedNotification;
use App\Services\Accounting\CreatePurchaseOrderJournalEntry;
use App\Support\NotificationRecipients;
use App\UserRole;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class PurchaseOrderObserver
{
    public function saved(PurchaseOrder $purchaseOrder): void
    {
        if ($purchaseOrder->wasChanged('status') && $purchaseOrder->status === 'approved') {
            $recipients = NotificationRecipients::roles(UserRole::Admin, UserRole::Operations, UserRole::Warehouse, UserRole::Finance);
            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new PurchaseOrderApprovedNotification($purchaseOrder));
            }
        }

        if ($purchaseOrder->wasChanged('status') && $purchaseOrder->status === 'cancelled') {
            $recipients = NotificationRecipients::roles(UserRole::Admin, UserRole::Operations, UserRole::Warehouse, UserRole::Finance);

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new PurchaseOrderCancelledNotification($purchaseOrder));
            }
        }

        if ($purchaseOrder->wasChanged('status') && $purchaseOrder->status === 'received') {
            Log::info('Triggering CreatePurchaseOrderJournalEntry');
            app(CreatePurchaseOrderJournalEntry::class)->handle($purchaseOrder);

            $recipients = NotificationRecipients::roles(UserRole::Admin, UserRole::Operations, UserRole::Warehouse, UserRole::Finance);

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new PurchaseOrderReceivedNotification($purchaseOrder));
            }
        }
    }
}

<?php

namespace App\Observers;

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Notifications\PurchaseOrderApprovedNotification;
use App\Services\Accounting\CreatePurchaseOrderJournalEntry;
use App\UserRole;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class PurchaseOrderObserver
{
    public function saved(PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->wasChanged('status') && $purchaseOrder->status === 'approved') {
            $recipients = User::whereIn('role', [UserRole::Admin->value, UserRole::Operations->value])->get();
            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new PurchaseOrderApprovedNotification($purchaseOrder));
            }
        }

        if ($purchaseOrder->wasChanged('status') && $purchaseOrder->status === 'received') {
            Log::info('Triggering CreatePurchaseOrderJournalEntry');
            app(CreatePurchaseOrderJournalEntry::class)->handle($purchaseOrder);
        }
    }
}

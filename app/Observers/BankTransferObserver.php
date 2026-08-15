<?php

namespace App\Observers;

use App\Models\BankTransfer;
use App\Notifications\BankTransferStatusChangedNotification;
use App\Support\Money;
use App\Support\NotificationRecipients;
use App\UserRole;
use Illuminate\Support\Facades\Notification;

class BankTransferObserver
{
    /**
     * Handle the BankTransfer "saving" event.
     */
    public function saving(BankTransfer $bankTransfer): void
    {
        if ((float) $bankTransfer->amount <= 0) {
            throw new \InvalidArgumentException('Transfer amount must be positive');
        }

        if ($bankTransfer->from_bank_id === $bankTransfer->to_bank_id) {
            throw new \InvalidArgumentException('Cannot transfer to the same bank');
        }

        if (
            $bankTransfer->isDirty('status') &&
            $bankTransfer->status === 'completed' &&
            $bankTransfer->getOriginal('status') !== 'completed'
        ) {
            $fromBank = $bankTransfer->fromBank()->lockForUpdate()->first();

            if (! $fromBank || (float) $fromBank->current_balance < (float) $bankTransfer->amount) {
                throw new \RuntimeException(sprintf(
                    'Insufficient balance in %s. Available: %s.',
                    $fromBank?->name ?? 'the selected bank',
                    Money::format($fromBank?->current_balance ?? 0)
                ));
            }
        }
    }

    /**
     * Handle the BankTransfer "updated" event.
     */
    public function updated(BankTransfer $bankTransfer): void
    {
        if (! $bankTransfer->wasChanged('status') || ! in_array($bankTransfer->status, ['completed', 'cancelled'], true)) {
            return;
        }

        if ($bankTransfer->status === 'completed') {
            $bankTransfer->fromBank()->decrement('current_balance', $bankTransfer->amount);
            $bankTransfer->toBank()->increment('current_balance', $bankTransfer->amount);
        }

        $recipients = NotificationRecipients::roles(UserRole::Admin, UserRole::Finance, UserRole::Operations);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new BankTransferStatusChangedNotification($bankTransfer->loadMissing(['fromBank', 'toBank'])));
        }
    }
}

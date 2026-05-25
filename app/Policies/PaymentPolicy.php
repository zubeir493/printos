<?php

namespace App\Policies;

use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function update(User $user, Payment $payment): bool
    {
        // Allow editing only if no journal entries exist
        return ! JournalEntry::where('source_type', Payment::class)
            ->where('source_id', $payment->id)
            ->exists();
    }

    public function delete(User $user, Payment $payment): bool
    {
        // Same logic for deletion
        return ! JournalEntry::where('source_type', Payment::class)
            ->where('source_id', $payment->id)
            ->exists();
    }

    public function void(User $user, Payment $payment): bool
    {
        return ! $payment->voided_at
            && JournalEntry::where('source_type', Payment::class)
                ->where('source_id', $payment->id)
                ->where('status', 'posted')
                ->exists();
    }
}

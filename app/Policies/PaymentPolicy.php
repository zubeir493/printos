<?php

namespace App\Policies;

use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\User;
use App\UserRole;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Finance], true);
    }

    public function view(User $user, Payment $payment): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Payment $payment): bool
    {
        // Allow editing only if no journal entries exist
        return $this->viewAny($user) && ! JournalEntry::where('source_type', Payment::class)
            ->where('source_id', $payment->id)
            ->exists();
    }

    public function delete(User $user, Payment $payment): bool
    {
        // Same logic for deletion
        return $this->viewAny($user) && ! JournalEntry::where('source_type', Payment::class)
            ->where('source_id', $payment->id)
            ->exists();
    }

    public function void(User $user, Payment $payment): bool
    {
        return $this->viewAny($user) && ! $payment->voided_at
            && JournalEntry::where('source_type', Payment::class)
                ->where('source_id', $payment->id)
                ->where('status', 'posted')
                ->exists();
    }
}

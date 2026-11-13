<?php

namespace App\Policies;

use App\Models\PayPolicy;
use App\Models\User;
use App\UserRole;

class PayPolicyPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Finance, UserRole::HR], true);
    }

    public function view(User $user, PayPolicy $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Finance], true);
    }

    public function update(User $user, PayPolicy $record): bool
    {
        return $this->create($user) && ! $record->assignments()->exists();
    }

    public function delete(User $user, PayPolicy $record): bool
    {
        return $this->update($user, $record);
    }

    public function replicate(User $user, PayPolicy $record): bool
    {
        return $this->create($user);
    }
}

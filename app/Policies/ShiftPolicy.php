<?php

namespace App\Policies;

use App\Models\Shift;
use App\Models\User;
use App\UserRole;

class ShiftPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::HR], true);
    }

    public function view(User $user, Shift $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Shift $record): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Shift $record): bool
    {
        return false;
    }
}

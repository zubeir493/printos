<?php

namespace App\Policies;

use App\Models\Holiday;
use App\Models\User;
use App\UserRole;

class HolidayPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::HR], true);
    }

    public function view(User $user, Holiday $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Holiday $record): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Holiday $record): bool
    {
        return false;
    }
}

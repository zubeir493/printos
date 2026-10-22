<?php

namespace App\Policies;

use App\Models\LeaveType;
use App\Models\User;
use App\UserRole;

class LeaveTypePolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::HR], true);
    }

    public function view(User $user, LeaveType $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, LeaveType $record): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, LeaveType $record): bool
    {
        return false;
    }
}

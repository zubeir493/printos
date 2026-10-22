<?php

namespace App\Policies;

use App\Models\EmployeeLoan;
use App\Models\User;
use App\UserRole;

class EmployeeLoanPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Finance], true);
    }

    public function view(User $user, EmployeeLoan $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, EmployeeLoan $record): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, EmployeeLoan $record): bool
    {
        return false;
    }
}

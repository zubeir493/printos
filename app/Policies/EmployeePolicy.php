<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;
use App\UserRole;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::HR, UserRole::Finance], true);
    }

    public function view(User $user, Employee $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::HR], true);
    }

    public function update(User $user, Employee $record): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::HR], true);
    }

    public function delete(User $user, Employee $record): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::HR], true);
    }
}

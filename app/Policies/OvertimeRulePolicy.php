<?php

namespace App\Policies;

use App\Models\OvertimeRule;
use App\Models\User;
use App\UserRole;

class OvertimeRulePolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Finance], true);
    }

    public function view(User $user, OvertimeRule $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, OvertimeRule $record): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, OvertimeRule $record): bool
    {
        return false;
    }
}

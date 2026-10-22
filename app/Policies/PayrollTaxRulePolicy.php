<?php

namespace App\Policies;

use App\Models\PayrollTaxRule;
use App\Models\User;
use App\UserRole;

class PayrollTaxRulePolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Finance], true);
    }

    public function view(User $user, PayrollTaxRule $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, PayrollTaxRule $record): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, PayrollTaxRule $record): bool
    {
        return false;
    }
}

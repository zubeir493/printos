<?php

namespace App\Policies;

use App\Models\PayrollRun;
use App\Models\User;
use App\UserRole;

class PayrollRunPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Finance], true);
    }

    public function view(User $user, PayrollRun $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, PayrollRun $record): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, PayrollRun $record): bool
    {
        return $this->viewAny($user) && $record->status === 'draft';
    }

    public function approve(User $user, PayrollRun $record): bool
    {
        return $this->viewAny($user) && $record->status === 'draft' && ($user->role === UserRole::Admin || $record->created_by !== $user->id);
    }
}

<?php

namespace App\Policies;

use App\Models\PayrollOvertimeEntry;
use App\Models\User;
use App\UserRole;

class PayrollOvertimeEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Finance, UserRole::HR], true);
    }

    public function view(User $user, PayrollOvertimeEntry $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, PayrollOvertimeEntry $record): bool
    {
        return $this->viewAny($user) && (! $record->payroll_run_id || $record->payrollRun()->where('status', 'draft')->exists());
    }

    public function delete(User $user, PayrollOvertimeEntry $record): bool
    {
        return false;
    }
}

<?php

namespace App\Policies;

use App\Models\JobOrder;
use App\Models\User;
use App\UserRole;

class JobOrderPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        $userRoleValue = $user->role?->value ?? $user->role;

        return in_array($userRoleValue, [
            UserRole::Admin->value,
            UserRole::Operations->value,
            UserRole::Production->value,
            UserRole::Design->value,
        ]);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, JobOrder $jobOrder): bool
    {
        $userRoleValue = $user->role?->value ?? $user->role;

        return in_array($userRoleValue, [
            UserRole::Admin->value,
            UserRole::Operations->value,
            UserRole::Production->value,
            UserRole::Design->value,
        ]);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        $userRoleValue = $user->role?->value ?? $user->role;

        return in_array($userRoleValue, [
            UserRole::Admin->value,
            UserRole::Operations->value,
        ]);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, JobOrder $jobOrder): bool
    {
        $userRoleValue = $user->role?->value ?? $user->role;

        return in_array($userRoleValue, [
            UserRole::Admin->value,
            UserRole::Operations->value,
        ]);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, JobOrder $jobOrder): bool
    {
        $userRoleValue = $user->role?->value ?? $user->role;

        return in_array($userRoleValue, [
            UserRole::Admin->value,
            UserRole::Operations->value,
        ]);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, JobOrder $jobOrder): bool
    {
        $userRoleValue = $user->role?->value ?? $user->role;

        return in_array($userRoleValue, [
            UserRole::Admin->value,
            UserRole::Operations->value,
        ]);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, JobOrder $jobOrder): bool
    {
        $userRoleValue = $user->role?->value ?? $user->role;

        return in_array($userRoleValue, [
            UserRole::Admin->value,
            UserRole::Operations->value,
        ]);
    }
}

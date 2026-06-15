<?php

namespace App\Policies;

use App\Models\Proforma;
use App\Models\User;
use App\UserRole;

class ProformaPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canAccessProformas($user);
    }

    public function view(User $user, Proforma $proforma): bool
    {
        return $this->canAccessProformas($user);
    }

    public function create(User $user): bool
    {
        return $this->canAccessProformas($user);
    }

    public function update(User $user, Proforma $proforma): bool
    {
        return $this->canAccessProformas($user) && $proforma->status === 'draft';
    }

    public function delete(User $user, Proforma $proforma): bool
    {
        return $this->canAccessProformas($user) && $proforma->status === 'draft';
    }

    public function restore(User $user, Proforma $proforma): bool
    {
        return $this->canAccessProformas($user);
    }

    public function forceDelete(User $user, Proforma $proforma): bool
    {
        return $this->canAccessProformas($user) && $proforma->status === 'draft';
    }

    private function canAccessProformas(User $user): bool
    {
        $userRoleValue = $user->role?->value ?? $user->role;

        return in_array($userRoleValue, [
            UserRole::Admin->value,
            UserRole::Finance->value,
            UserRole::Operations->value,
        ], true);
    }
}

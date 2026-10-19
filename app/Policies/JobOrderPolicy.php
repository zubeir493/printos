<?php

namespace App\Policies;

use App\Models\JobOrder;
use App\Models\User;
use App\UserRole;

class JobOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Operations, UserRole::Production, UserRole::Design, UserRole::Typist, UserRole::Finance, UserRole::Warehouse], true);
    }

    public function view(User $user, JobOrder $jobOrder): bool
    {
        if ($user->role === UserRole::Design) {
            return $jobOrder->designer_id === $user->id;
        }
        if ($user->role === UserRole::Typist) {
            return $jobOrder->typist_id === $user->id;
        }

        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Operations], true);
    }

    public function update(User $user, JobOrder $jobOrder): bool
    {
        return $this->create($user) && (string) $jobOrder->status === 'draft' && blank($jobOrder->proforma_task_id);
    }

    public function manageWorkflow(User $user, JobOrder $jobOrder): bool
    {
        return $this->create($user) && ! in_array((string) $jobOrder->status, ['completed', 'cancelled'], true);
    }

    public function logProduction(User $user, JobOrder $jobOrder): bool
    {
        return $this->create($user) || $user->role === UserRole::Production;
    }

    public function recordPayment(User $user, JobOrder $jobOrder): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Finance, UserRole::Operations], true)
            && $jobOrder->production_mode === 'make_to_order'
            && ! in_array((string) $jobOrder->status, ['draft', 'cancelled'], true);
    }

    public function manageMaterials(User $user, JobOrder $jobOrder): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Operations, UserRole::Warehouse], true)
            && in_array((string) $jobOrder->status, ['design', 'production'], true);
    }

    public function returnMaterials(User $user, JobOrder $jobOrder): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Operations, UserRole::Warehouse], true);
    }

    public function delete(User $user, JobOrder $jobOrder): bool
    {
        return $this->create($user) && (string) $jobOrder->status === 'draft' && blank($jobOrder->proforma_task_id) && ! $jobOrder->payments()->exists()
            && ! $jobOrder->invoices()->exists() && ! $jobOrder->materialMovements()->exists();
    }

    public function deleteAny(User $user): bool
    {
        return $this->create($user);
    }

    public function restore(User $user, JobOrder $jobOrder): bool
    {
        return $this->create($user);
    }

    public function forceDelete(User $user, JobOrder $jobOrder): bool
    {
        return $this->delete($user, $jobOrder);
    }
}

<?php

namespace App\Policies;

use App\Models\Artwork;
use App\Models\JobOrder;
use App\Models\User;
use App\UserRole;

class ArtworkPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Operations, UserRole::Design, UserRole::Production, UserRole::Warehouse], true);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Operations, UserRole::Design], true);
    }

    public function update(User $user, Artwork $artwork): bool
    {
        return $this->uploadToJob($user, $artwork->jobOrder);
    }

    public function uploadToJob(User $user, ?JobOrder $jobOrder): bool
    {
        return $jobOrder && (string) $jobOrder->status !== 'cancelled'
            && (in_array($user->role, [UserRole::Admin, UserRole::Operations], true)
                || ($user->role === UserRole::Design && $jobOrder->designer_id === $user->id));
    }

    public function approve(User $user, Artwork $artwork): bool
    {
        return $artwork->jobOrder && (string) $artwork->jobOrder->status !== 'cancelled'
            && in_array($user->role, [UserRole::Admin, UserRole::Operations], true);
    }

    public function delete(User $user, Artwork $artwork): bool
    {
        return $this->update($user, $artwork);
    }

    public function deleteAny(User $user): bool
    {
        return $this->create($user);
    }
}

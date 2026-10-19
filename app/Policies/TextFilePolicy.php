<?php

namespace App\Policies;

use App\Models\JobOrder;
use App\Models\TextFile;
use App\Models\User;
use App\UserRole;

class TextFilePolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Operations, UserRole::Typist, UserRole::Production, UserRole::Warehouse], true);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Operations, UserRole::Typist], true);
    }

    public function update(User $user, TextFile $textFile): bool
    {
        return $this->uploadToJob($user, $textFile->jobOrder);
    }

    public function uploadToJob(User $user, ?JobOrder $jobOrder): bool
    {
        return $jobOrder && (string) $jobOrder->status !== 'cancelled'
            && (in_array($user->role, [UserRole::Admin, UserRole::Operations], true)
                || ($user->role === UserRole::Typist && $jobOrder->typist_id === $user->id));
    }

    public function approve(User $user, TextFile $textFile): bool
    {
        return $textFile->jobOrder && (string) $textFile->jobOrder->status !== 'cancelled'
            && in_array($user->role, [UserRole::Admin, UserRole::Operations], true);
    }

    public function delete(User $user, TextFile $textFile): bool
    {
        return $this->update($user, $textFile);
    }

    public function deleteAny(User $user): bool
    {
        return $this->create($user);
    }
}

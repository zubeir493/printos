<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkSchedule;
use App\UserRole;

class WorkSchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Finance, UserRole::HR], true);
    }

    public function view(User $user, WorkSchedule $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::HR], true);
    }

    public function update(User $user, WorkSchedule $record): bool
    {
        return $this->create($user) && ! $record->assignments()->exists();
    }

    public function delete(User $user, WorkSchedule $record): bool
    {
        return $this->update($user, $record);
    }

    public function replicate(User $user, WorkSchedule $record): bool
    {
        return $this->create($user);
    }
}

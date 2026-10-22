<?php

namespace App\Policies;

use App\Models\AttendanceSegment;
use App\Models\User;
use App\UserRole;

class AttendanceSegmentPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::HR], true);
    }

    public function view(User $user, AttendanceSegment $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, AttendanceSegment $record): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, AttendanceSegment $record): bool
    {
        return false;
    }
}

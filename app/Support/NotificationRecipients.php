<?php

namespace App\Support;

use App\Models\User;
use App\Models\Warehouse;
use App\UserRole;
use Illuminate\Support\Collection;

class NotificationRecipients
{
    /**
     * @return Collection<int, User>
     */
    public static function roles(UserRole ...$roles): Collection
    {
        return User::query()
            ->whereIn('role', collect($roles)->map->value)
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    public static function inventoryUsers(?Warehouse $warehouse = null): Collection
    {
        $users = self::roles(UserRole::Warehouse);

        if ($warehouse) {
            $users = $users->merge($warehouse->users()->get());
        }

        return $users->unique('id')->values();
    }
}

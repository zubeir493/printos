<?php

namespace App\Support;

use App\Models\User;
use App\Models\Warehouse;
use App\UserRole;
use Illuminate\Database\Eloquent\Builder;
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
        return $warehouse ? $warehouse->users()->get() : collect();
    }

    /**
     * @return Collection<int, User>
     */
    public static function warehouseStaff(int ...$warehouseIds): Collection
    {
        $staff = User::query()->where('role', UserRole::Warehouse);

        foreach ($warehouseIds as $warehouseId) {
            $staff->whereHas('warehouses', fn (Builder $query): Builder => $query->whereKey($warehouseId));
        }

        return $staff->get();
    }
}

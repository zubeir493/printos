<?php

namespace App\Support;

use App\Models\User;
use App\Models\Warehouse;
use App\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WarehouseAccess
{
    public static function scope(Builder $query, string ...$warehouseColumns): Builder
    {
        $actor = auth()->user();

        if ($actor?->role === UserRole::Warehouse) {
            $assigned = $actor->warehouses()->select('warehouses.id')->getQuery();

            foreach ($warehouseColumns as $column) {
                $query->whereIn($column, $assigned);
            }
        }

        return $query;
    }

    public static function warehouses(): Builder
    {
        return self::scope(Warehouse::query(), 'warehouses.id');
    }

    public static function defaultId(): ?int
    {
        $warehouses = self::warehouses();

        if (auth()->user()?->role !== UserRole::Warehouse) {
            return $warehouses->where('is_default', true)->value('id');
        }

        return $warehouses->orderByDesc('is_default')->orderBy('name')->orderBy('id')->value('id');
    }

    public static function allows(int $warehouseId, ?User $actor = null): bool
    {
        $actor ??= auth()->user();

        return $actor?->role !== UserRole::Warehouse
            || $actor->warehouses()->whereKey($warehouseId)->exists();
    }

    public static function authorize(int $warehouseId, ?User $actor = null): void
    {
        abort_unless(self::allows($warehouseId, $actor), 403, 'You are not assigned to this warehouse.');
    }

    public static function authorizeRecord(Model $record, string ...$warehouseColumns): void
    {
        if (auth()->user()?->role !== UserRole::Warehouse) {
            return;
        }

        foreach ($warehouseColumns as $column) {
            self::authorize((int) $record->getAttribute($column));

            if ($record->exists && $record->isDirty($column)) {
                self::authorize((int) $record->getRawOriginal($column));
            }
        }
    }
}

<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\Users\UserResource;
use App\UserRole;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        $warehouseIds = $this->data['warehouse_ids'] ?? [];
        $role = $this->record->role?->value ?? $this->record->role;

        $this->record->warehouses()->sync($role === UserRole::Warehouse->value ? $warehouseIds : []);
    }
}

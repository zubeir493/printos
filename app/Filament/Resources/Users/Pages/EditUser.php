<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\UserRole;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['warehouse_ids'] = $this->record->warehouses()->pluck('warehouses.id')->all();

        return $data;
    }

    protected function afterSave(): void
    {
        $warehouseIds = $this->data['warehouse_ids'] ?? [];
        $role = $this->record->role?->value ?? $this->record->role;

        $this->record->warehouses()->sync($role === UserRole::Warehouse->value ? $warehouseIds : []);
    }
}

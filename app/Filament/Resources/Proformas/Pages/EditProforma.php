<?php

namespace App\Filament\Resources\Proformas\Pages;

use App\Filament\Resources\Proformas\Actions\ProformaActions;
use App\Filament\Resources\Proformas\ProformaResource;
use Filament\Resources\Pages\EditRecord;

class EditProforma extends EditRecord
{
    protected static string $resource = ProformaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ProformaActions::make(includeDelete: true),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['tasks']);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->recalculateTotals();
    }
}

<?php

namespace App\Filament\Resources\Proformas\Pages;

use App\Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\Proformas\ProformaResource;

class CreateProforma extends CreateRecord
{
    protected static string $resource = ProformaResource::class;

    protected static bool $canCreateAnother = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = 'draft';

        unset($data['tasks']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->recalculateTotals();
    }
}

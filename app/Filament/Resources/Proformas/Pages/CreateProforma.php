<?php

namespace App\Filament\Resources\Proformas\Pages;

use App\Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\Proformas\ProformaResource;
use App\Services\CostEstimates\CostEstimateCalculator;

class CreateProforma extends CreateRecord
{
    protected static string $resource = ProformaResource::class;

    protected static bool $canCreateAnother = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $calculation = app(CostEstimateCalculator::class)->calculate(
            $data['job_type'],
            $data['tasks'] ?? [],
        );

        $data['tasks'] = $calculation['tasks'];
        $data['subtotal'] = $calculation['subtotal'];
        $data['tax_amount'] = $calculation['tax_amount'];
        $data['total'] = $calculation['total'];
        $data['status'] = 'draft';

        unset($data['tasks']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->recalculateTotals();
    }
}

<?php

namespace App\Filament\Resources\CostEstimates\Pages;

use App\Filament\Resources\CostEstimates\CostEstimateResource;
use App\Services\CostEstimates\CostEstimateCalculator;
use Filament\Resources\Pages\CreateRecord;

class CreateCostEstimate extends CreateRecord
{
    protected static string $resource = CostEstimateResource::class;

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

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->recalculateTotals();
    }
}

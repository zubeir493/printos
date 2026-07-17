<?php

namespace App\Filament\Resources\CostEstimates\Pages;

use App\Filament\Resources\CostEstimates\Actions\CostEstimateActions;
use App\Filament\Resources\CostEstimates\CostEstimateResource;
use App\Services\Costing\CostEstimateService;
use Filament\Resources\Pages\EditRecord;

class EditCostEstimate extends EditRecord
{
    protected static string $resource = CostEstimateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CostEstimateActions::make(includeDelete: true),
        ];
    }

    protected function afterSave(): void
    {
        app(CostEstimateService::class)->recalculate($this->record);
    }
}

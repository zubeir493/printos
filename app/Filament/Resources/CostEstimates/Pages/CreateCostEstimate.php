<?php

namespace App\Filament\Resources\CostEstimates\Pages;

use App\Filament\Resources\CostEstimates\CostEstimateResource;
use App\Filament\Resources\Pages\CreateRecord;
use App\Services\Costing\CostEstimateService;

class CreateCostEstimate extends CreateRecord
{
    protected static string $resource = CostEstimateResource::class;

    protected function afterCreate(): void
    {
        app(CostEstimateService::class)->recalculate($this->record);
    }
}

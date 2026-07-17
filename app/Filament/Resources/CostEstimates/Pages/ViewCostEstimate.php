<?php

namespace App\Filament\Resources\CostEstimates\Pages;

use App\Filament\Resources\CostEstimates\Actions\CostEstimateActions;
use App\Filament\Resources\CostEstimates\CostEstimateResource;
use Filament\Resources\Pages\ViewRecord;

class ViewCostEstimate extends ViewRecord
{
    protected static string $resource = CostEstimateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CostEstimateActions::make(),
        ];
    }
}

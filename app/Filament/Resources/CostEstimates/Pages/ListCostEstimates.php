<?php

namespace App\Filament\Resources\CostEstimates\Pages;

use App\Filament\Resources\CostEstimates\CostEstimateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCostEstimates extends ListRecords
{
    protected static string $resource = CostEstimateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

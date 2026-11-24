<?php

namespace App\Filament\Resources\CostEstimates\Pages;

use App\Filament\Resources\CostEstimates\CostEstimateResource;
use App\Filament\Resources\CostEstimates\Pages\Concerns\HasEstimatorWorkspace;
use App\Filament\Resources\Pages\CreateRecord;
use App\Services\Costing\CostEstimateService;
use Filament\Actions\Action;

class CreateCostEstimate extends CreateRecord
{
    use HasEstimatorWorkspace;

    protected ?bool $hasDatabaseTransactions = true;

    protected static string $resource = CostEstimateResource::class;

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Save estimate');
    }

    protected function afterCreate(): void
    {
        $this->record = app(CostEstimateService::class)->recalculate($this->record);
        unset($this->estimatorResult, $this->estimatorComparisons);
    }
}

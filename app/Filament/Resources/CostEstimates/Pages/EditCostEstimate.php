<?php

namespace App\Filament\Resources\CostEstimates\Pages;

use App\Filament\Resources\CostEstimates\Actions\CostEstimateActions;
use App\Filament\Resources\CostEstimates\CostEstimateResource;
use App\Filament\Resources\CostEstimates\Pages\Concerns\HasEstimatorWorkspace;
use App\Services\Costing\CostEstimateService;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditCostEstimate extends EditRecord
{
    use HasEstimatorWorkspace;

    protected ?bool $hasDatabaseTransactions = true;

    protected static string $resource = CostEstimateResource::class;

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->label('Save estimate');
    }

    protected function getHeaderActions(): array
    {
        return [
            CostEstimateActions::make(includeDelete: true),
        ];
    }

    protected function afterSave(): void
    {
        $this->record = app(CostEstimateService::class)->recalculate($this->record);
        unset($this->estimatorResult, $this->estimatorComparisons);
    }
}

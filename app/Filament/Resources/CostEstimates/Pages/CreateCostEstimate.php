<?php

namespace App\Filament\Resources\CostEstimates\Pages;

use App\Filament\Resources\CostEstimates\CostEstimateResource;
use App\Filament\Resources\CostEstimates\Pages\Concerns\HasEstimatorWorkspace;
use App\Filament\Resources\Pages\CreateRecord;
use App\Services\Costing\CostEstimateService;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;

class CreateCostEstimate extends CreateRecord
{
    use HasEstimatorWorkspace;

    protected ?bool $hasDatabaseTransactions = true;

    protected string $view = 'filament.resources.cost-estimates.pages.estimator-workspace';

    protected Width|string|null $maxContentWidth = Width::Full;

    protected static string $resource = CostEstimateResource::class;

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function backUrl(): string
    {
        return CostEstimateResource::getUrl('index');
    }

    public function backLabel(): string
    {
        return 'Back to estimates';
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function afterCreate(): void
    {
        $this->record = app(CostEstimateService::class)->recalculate($this->record);
        unset($this->estimatorResult, $this->estimatorComparisons);
    }
}

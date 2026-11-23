<?php

namespace App\Filament\Resources\CostEstimates\Pages;

use App\Filament\Resources\CostEstimates\Actions\CostEstimateActions;
use App\Filament\Resources\CostEstimates\CostEstimateResource;
use App\Filament\Resources\CostEstimates\Pages\Concerns\HasEstimatorWorkspace;
use App\Services\Costing\CostEstimateService;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;

class EditCostEstimate extends EditRecord
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
        return CostEstimateResource::getUrl('view', ['record' => $this->record]);
    }

    public function backLabel(): string
    {
        return 'Back to estimate';
    }

    public function getHeader(): ?View
    {
        return view('filament.resources.cost-estimates.pages.estimator-edit-header');
    }

    protected function getFormActions(): array
    {
        return $this->record->formula_version === 'sheetfed-v2'
            ? []
            : [$this->getSaveFormAction()->label('Save estimate')];
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

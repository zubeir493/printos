<?php

namespace App\Filament\Resources\CostEstimates\Pages\Concerns;

use App\Filament\Resources\CostEstimates\CostEstimateResource;
use App\Filament\Resources\CostEstimates\Pages\ViewCostEstimate;
use App\Models\CostEstimate;
use App\Services\Costing\CostingResult;
use App\Services\Costing\EstimatorRecipe;
use App\Services\Costing\EstimatorRequestContext;
use App\Services\Costing\SheetfedCostCalculator;
use Livewire\Attributes\Computed;

trait HasEstimatorWorkspace
{
    public string $estimatorTab = 'configure';

    public function estimatorCanEdit(): bool
    {
        return ! $this instanceof ViewCostEstimate && CostEstimateResource::canEdit($this->record ?? null);
    }

    #[Computed]
    public function estimatorConfigurator(): array
    {
        $sources = app(EstimatorRequestContext::class);
        $key = app(EstimatorRecipe::class)->productKey($this->data ?? []);

        return [
            'key' => $key, 'preset' => EstimatorRecipe::PRODUCTS[$key], 'products' => EstimatorRecipe::PRODUCTS,
            'materials' => $sources->materialOptions(['paper', 'board', 'liner']),
            'customers' => $sources->customerOptions(),
            'components' => $this->data['services']['components'] ?? [],
            'route' => collect($this->data['services']['finishing'] ?? [])->map(fn (array $row): array => [
                'label' => $row['name'] ?? 'Finishing',
                'machine' => $sources->machine($row['machine_id'] ?? null)?->name,
            ])->all(),
            'editable' => $this->estimatorCanEdit(),
        ];
    }

    public function selectEstimatorProduct(string $key): void
    {
        abort_unless($this->estimatorCanEdit(), 403);
        abort_unless(isset(EstimatorRecipe::PRODUCTS[$key]), 422);
        $this->data = app(EstimatorRecipe::class)->apply($this->data ?? [], $key);
        $this->estimatorTab = 'configure';
        $this->clearEstimatorPreview();
        $this->dispatch('close-modal', id: 'estimator-product-picker');
    }

    public function addEstimatorFinish(string $operation): void
    {
        abort_unless($this->estimatorCanEdit(), 403);
        abort_unless(in_array($operation, ['laminating', 'foiling', 'embossing', 'coating'], true), 422);
        $this->data = app(EstimatorRecipe::class)->addFinish($this->data, $operation);
        $this->clearEstimatorPreview();
        $this->dispatch('close-modal', id: 'estimator-finish-picker');
    }

    public function updatedData(mixed $value, string $key): void
    {
        if (! $this->estimatorCanEdit()) {
            return;
        }
        $geometryChange = in_array($key, ['services.product.width_mm', 'services.product.height_mm', 'services.product.page_count', 'services.product.shape'], true);
        if ($geometryChange) {
            foreach ($this->data['services']['components'] ?? [] as $rowKey => $component) {
                $this->data['services']['components'][$rowKey]['layout_confirmed'] = false;
                $this->data['services']['components'][$rowKey]['layout_key'] = null;
                if (in_array($this->data['job_type'] ?? '', ['labels', 'stickers'], true) && ($component['role'] ?? '') === 'stock' && (str_ends_with($key, 'width_mm') || str_ends_with($key, 'height_mm'))) {
                    $dimension = str_ends_with($key, 'width_mm') ? 'width_mm' : 'height_mm';
                    $this->data['services']['components'][$rowKey][$dimension] = $value;
                }
            }
        }
        if (str_starts_with($key, 'services.consumables.') || str_starts_with($key, 'services.finishing.')) {
            $this->data['services']['product']['consumables_confirmed'] = false;
        }
        if (preg_match('/^services\.components\.([^.]+)\.(material_id|front_colors|back_colors)$/', $key, $matches)) {
            $recipe = app(EstimatorRecipe::class);
            if ($matches[2] === 'material_id') {
                $this->data = $recipe->apply($this->data, $recipe->productKey($this->data));
            }
            $component = $this->data['services']['components'][$matches[1]];
            $component['layout_confirmed'] = false;
            $component['layout_key'] = null;
            $this->data['services']['components'][$matches[1]] = $recipe->prepareComponent(
                $component, $this->data['services']['product'] ?? [], $this->data['job_type'] ?? 'labels',
            );
        }
        $this->clearEstimatorPreview();
    }

    private function clearEstimatorPreview(): void
    {
        unset($this->estimatorResult, $this->estimatorComparisons, $this->estimatorConfigurator);
    }

    public function estimatorIssueTarget(string $message): string
    {
        $message = strtolower($message);
        if (str_contains($message, 'margin') || str_contains($message, 'discount') || str_contains($message, 'overhead')) {
            return 'pricing';
        }
        if (str_contains($message, 'quantity between') || str_contains($message, 'product family')) {
            return 'specifications';
        }
        if (str_contains($message, 'consum') || str_contains($message, 'tooling') || str_contains($message, 'production route') || str_contains($message, 'binding glue') || str_contains($message, 'binding wire')) {
            return 'production';
        }

        return 'materials';
    }

    #[Computed]
    public function estimatorResult(): CostingResult
    {
        $record = $this->record ?? null;
        if ($record instanceof CostEstimate && ! $record->isEditable() && $record->formula_version === 'sheetfed-v2') {
            $snapshot = $record->calculation_snapshot ?? [];

            return new CostingResult(
                subtotal: (float) $record->subtotal, overheadAmount: (float) $record->overhead_amount,
                profitAmount: (float) $record->profit_amount, discountAmount: (float) $record->discount_amount,
                vatRate: (float) $record->vat_rate, taxAmount: (float) $record->tax_amount, total: (float) $record->total,
                unitPrice: (float) $record->unit_price, marginPercent: (float) $record->margin_percent,
                lines: $record->lines->toArray(), materialConsumption: $snapshot['material_consumption'] ?? [], machineUsage: $snapshot['machine_usage'] ?? [],
                settingsSnapshot: $record->settings_snapshot ?? [], formulaVersion: 'sheetfed-v2', diagnostics: $snapshot['diagnostics'] ?? [],
                layouts: $snapshot['layouts'] ?? [], operations: $snapshot['operations'] ?? [], commercial: $snapshot['commercial'] ?? [],
            );
        }

        return app(SheetfedCostCalculator::class)->calculate($this->data ?? [], fresh: false);
    }

    #[Computed]
    public function estimatorComparisons(): array
    {
        if (! $this->estimatorResult->isComplete()) {
            return [];
        }
        $record = $this->record ?? null;
        if ($record instanceof CostEstimate && ! $record->isEditable()) {
            return [['quantity' => $record->quantity, 'result' => $this->estimatorResult]];
        }
        $quantities = collect(explode(',', (string) ($this->data['services']['comparison_quantities'] ?? '')))
            ->filter(fn (string $value): bool => ctype_digit(trim($value)))
            ->map(fn (string $value): int => (int) trim($value))
            ->filter(fn (int $value): bool => $value > 0 && $value <= 100000000)->unique()->take(3)
            ->push((int) ($this->data['quantity'] ?? 0))->unique()->sort();
        $calculator = app(SheetfedCostCalculator::class);

        return $quantities->map(fn (int $quantity): array => [
            'quantity' => $quantity,
            'result' => $quantity === (int) ($this->data['quantity'] ?? 0)
                ? $this->estimatorResult
                : $calculator->calculate([...$this->data, 'quantity' => $quantity], fresh: false),
        ])->values()->all();
    }

    public function applyEstimatorTemplate(string $type, string $binding = 'perfect'): void
    {
        abort_unless($this->estimatorCanEdit(), 403);
        if (! in_array($type, ['books', 'labels', 'packages', 'stickers'], true)) {
            return;
        }
        $key = app(EstimatorRecipe::class)->productKey(['job_type' => $type, 'services' => ['product' => ['binding' => $binding]]]);
        $this->data = app(EstimatorRecipe::class)->apply($this->data ?? [], $key, productChanged: true);
        $this->data['services']['product']['consumables_confirmed'] = false;
        foreach ($this->data['services']['components'] as &$component) {
            $component['layout_confirmed'] = false;
            $component['layout_key'] = null;
        }
        unset($component);
        $this->clearEstimatorPreview();
    }
}

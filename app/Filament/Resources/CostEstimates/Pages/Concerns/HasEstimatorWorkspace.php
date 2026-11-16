<?php

namespace App\Filament\Resources\CostEstimates\Pages\Concerns;

use App\Filament\Resources\CostEstimates\Schemas\EstimatorWorkspace;
use App\Models\CostEstimate;
use App\Services\Costing\CostingResult;
use App\Services\Costing\SheetfedCostCalculator;
use Livewire\Attributes\Computed;

trait HasEstimatorWorkspace
{
    public string $estimatorTab = 'specifications';

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
        if (! in_array($type, ['books', 'labels', 'packages', 'stickers'], true)) {
            return;
        }
        $template = EstimatorWorkspace::template($type, $binding);
        $this->data['job_type'] = $type;
        $this->data['services']['costing_version'] = 'sheetfed-v2';
        $this->data['services']['product']['binding'] = $binding;
        $this->data['services']['product']['consumables_confirmed'] = false;
        $this->data['services']['components'] = $template['components'];
        $this->data['services']['finishing'] = $template['finishing'];
        $this->data['services']['consumables'] = [];
        unset($this->estimatorResult, $this->estimatorComparisons);
    }
}

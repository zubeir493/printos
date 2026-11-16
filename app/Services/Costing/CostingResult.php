<?php

namespace App\Services\Costing;

class CostingResult
{
    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @param  array<int, array<string, mixed>>  $materialConsumption
     * @param  array<int, array<string, mixed>>  $machineUsage
     * @param  array<string, mixed>  $settingsSnapshot
     */
    public function __construct(
        public float $subtotal,
        public float $overheadAmount,
        public float $profitAmount,
        public float $discountAmount,
        public float $vatRate,
        public float $taxAmount,
        public float $total,
        public float $unitPrice,
        public float $marginPercent,
        public array $lines,
        public array $materialConsumption,
        public array $machineUsage,
        public array $settingsSnapshot,
        public string $formulaVersion = 'v1',
        public array $diagnostics = [],
        public array $layouts = [],
        public array $operations = [],
        public array $commercial = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function totals(): array
    {
        return [
            'subtotal' => round($this->subtotal, 2),
            'overhead_amount' => round($this->overheadAmount, 2),
            'profit_amount' => round($this->profitAmount, 2),
            'discount_amount' => round($this->discountAmount, 2),
            'vat_rate' => round($this->vatRate, 2),
            'tax_amount' => round($this->taxAmount, 2),
            'total' => round($this->total, 2),
            'unit_price' => round($this->unitPrice, $this->formulaVersion === 'sheetfed-v2' ? 6 : 4),
            'margin_percent' => round($this->marginPercent, 2),
            'formula_version' => $this->formulaVersion,
            'settings_snapshot' => $this->settingsSnapshot,
        ];
    }

    public function isComplete(): bool
    {
        return $this->diagnostics === [];
    }

    public function calculationSnapshot(): array
    {
        return [
            'diagnostics' => $this->diagnostics,
            'layouts' => $this->layouts,
            'operations' => $this->operations,
            'commercial' => $this->commercial,
            'material_consumption' => $this->materialConsumption,
            'machine_usage' => $this->machineUsage,
        ];
    }
}

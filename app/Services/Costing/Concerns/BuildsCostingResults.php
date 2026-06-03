<?php

namespace App\Services\Costing\Concerns;

use App\Models\InventoryItem;
use App\Models\Machine;
use App\Models\Setting;
use App\Services\Costing\CostingResult;

trait BuildsCostingResults
{
    protected function settingsSnapshot(Setting $settings, float $overheadPercent, float $profitPercent, float $discountPercent): array
    {
        return [
            'vat_enabled' => $settings->vat_enabled,
            'vat_rate' => (float) $settings->vat_rate,
            'overhead_percent' => $overheadPercent,
            'profit_margin_percent' => $profitPercent,
            'discount_percent' => $discountPercent,
            'costing_defaults' => $settings->costing_defaults ?? [],
        ];
    }

    protected function commercialTotals(array $lines, int $quantity, array $commercial, Setting $settings): CostingResult
    {
        $subtotal = round((float) collect($lines)->sum('total'), 2);
        $overheadPercent = (float) ($commercial['overhead_percent'] ?? ($settings->costing_defaults['overhead_percent'] ?? 15));
        $profitPercent = (float) ($commercial['profit_margin_percent'] ?? ($settings->costing_defaults['profit_margin_percent'] ?? 20));
        $discountPercent = (float) ($commercial['discount_percent'] ?? 0);

        $overhead = round($subtotal * $overheadPercent / 100, 2);
        $costWithOverhead = $subtotal + $overhead;
        $profit = round($costWithOverhead * $profitPercent / 100, 2);
        $discount = round(($costWithOverhead + $profit) * $discountPercent / 100, 2);
        $beforeVat = max(0, $costWithOverhead + $profit - $discount);
        $vatRate = $settings->vat_enabled ? (float) $settings->vat_rate : 0.0;
        $vat = round($beforeVat * $vatRate / 100, 2);
        $total = round($beforeVat + $vat, 2);
        $unitPrice = $quantity > 0 ? $total / $quantity : 0.0;

        return new CostingResult(
            subtotal: $subtotal,
            overheadAmount: $overhead,
            profitAmount: $profit,
            discountAmount: $discount,
            vatRate: $vatRate,
            taxAmount: $vat,
            total: $total,
            unitPrice: $unitPrice,
            marginPercent: $profitPercent,
            lines: array_values($lines),
            materialConsumption: $this->materialConsumption($lines),
            settingsSnapshot: $this->settingsSnapshot($settings, $overheadPercent, $profitPercent, $discountPercent),
        );
    }

    protected function inventorySnapshot(?InventoryItem $item): array
    {
        if (! $item) {
            return [];
        }

        return [
            'inventory_item_id' => $item->id,
            'name' => $item->name,
            'sku' => $item->sku,
            'unit' => $item->unit,
            'average_cost' => (float) $item->average_cost,
            'base_unit_cost' => $item->baseUnitCost(),
            'price' => (float) ($item->price ?? 0),
            'purchase_unit' => $item->purchase_unit,
            'conversion_factor' => (float) ($item->conversion_factor ?? 0),
            'price_per_purchase_unit' => $item->pricePerPurchaseUnit(),
            'gsm' => (float) ($item->gsm ?? 0),
            'width' => (float) ($item->width ?? 0),
            'height' => (float) ($item->height ?? 0),
            'default_waste_percent' => (float) ($item->default_waste_percent ?? 0),
            'stock_on_hand' => $item->stockOnHand(),
        ];
    }

    protected function machineSnapshot(?Machine $machine): array
    {
        if (! $machine) {
            return [];
        }

        return [
            'machine_id' => $machine->id,
            'name' => $machine->name,
            'code' => $machine->code,
            'hourly_cost' => (float) $machine->hourly_cost,
            'production_speed' => (float) $machine->production_speed,
            'operation_type' => $machine->operation_type,
        ];
    }

    protected function line(string $category, string $label, float $quantity, ?string $unit, float $unitCost, ?InventoryItem $item = null, array $snapshot = []): array
    {
        return [
            'category' => $category,
            'label' => $label,
            'inventory_item_id' => $item?->id,
            'quantity' => round($quantity, 4),
            'unit' => $unit,
            'unit_cost' => round($unitCost, 4),
            'total' => round($quantity * $unitCost, 2),
            'snapshot' => array_filter([
                'inventory' => $this->inventorySnapshot($item),
                ...$snapshot,
            ]),
        ];
    }

    protected function itemCost(?InventoryItem $item, float $fallback = 0): float
    {
        if (! $item) {
            return $fallback;
        }

        $baseUnitCost = $item->baseUnitCost();

        return $baseUnitCost > 0 ? $baseUnitCost : $fallback;
    }

    protected function materialConsumption(array $lines): array
    {
        return collect($lines)
            ->filter(fn (array $line): bool => filled($line['inventory_item_id'] ?? null))
            ->map(fn (array $line): array => [
                'inventory_item_id' => $line['inventory_item_id'],
                'label' => $line['label'],
                'name' => $line['snapshot']['inventory']['name'] ?? $line['label'],
                'inventory' => $line['snapshot']['inventory'] ?? [],
                'quantity' => $line['quantity'],
                'unit' => $line['unit'],
            ])
            ->values()
            ->all();
    }
}

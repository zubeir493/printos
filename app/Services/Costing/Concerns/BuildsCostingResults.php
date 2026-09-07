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
        $subtotal = (float) collect($lines)->sum('total');
        $overheadPercent = (float) ($commercial['overhead_percent'] ?? ($settings->costing_defaults['overhead_percent'] ?? 15));
        $profitPercent = (float) ($commercial['profit_margin_percent'] ?? ($settings->costing_defaults['profit_margin_percent'] ?? 20));
        $discountPercent = (float) ($commercial['discount_percent'] ?? 0);

        $overhead = $subtotal * $overheadPercent / 100;
        $costWithOverhead = $subtotal + $overhead;
        $profit = $costWithOverhead * $profitPercent / 100;
        $discount = ($costWithOverhead + $profit) * $discountPercent / 100;
        $beforeVat = max(0, $costWithOverhead + $profit - $discount);
        $vatRate = $settings->vat_enabled ? (float) $settings->vat_rate : 0.0;
        $vat = $beforeVat * $vatRate / 100;
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
            machineUsage: $this->machineUsage($lines),
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
            'total' => $quantity * $unitCost,
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
            ->filter(fn (array $line): bool => (float) ($line['quantity'] ?? 0) > 0
                && (filled($line['inventory_item_id'] ?? null) || in_array($line['category'] ?? null, ['Material', 'Ink', 'Finishing', 'Packing'], true)))
            ->map(fn (array $line): array => [
                'inventory_item_id' => $line['inventory_item_id'],
                'label' => $line['label'],
                'name' => $line['snapshot']['inventory']['name'] ?? $line['label'],
                'inventory' => $line['snapshot']['inventory'] ?? [],
                'quantity' => $line['quantity'],
                'unit' => $line['unit'],
            ])
            ->groupBy(fn (array $line): string => ($line['inventory_item_id'] ?? $line['name']).'|'.($line['unit'] ?? ''))
            ->map(function ($lines): array {
                $first = $lines->first();

                return [
                    ...$first,
                    'quantity' => round((float) $lines->sum('quantity'), 4),
                    'label' => $lines->pluck('label')->unique()->implode(', '),
                ];
            })
            ->values()
            ->all();
    }

    protected function machineUsage(array $lines): array
    {
        return collect($lines)
            ->filter(fn (array $line): bool => (float) ($line['quantity'] ?? 0) > 0
                && (filled($line['snapshot']['machine']['machine_id'] ?? null) || ($line['category'] ?? null) === 'Machine'))
            ->map(fn (array $line): array => [
                'machine_id' => $line['snapshot']['machine']['machine_id'] ?? null,
                'label' => $line['label'],
                'name' => $line['snapshot']['machine']['name'] ?? $line['label'],
                'machine' => $line['snapshot']['machine'] ?? [],
                'costing_speed' => $line['snapshot']['costing_speed'] ?? null,
                'quantity' => $line['quantity'],
                'unit' => $line['unit'],
            ])
            ->groupBy(fn (array $line): string => ($line['machine_id'] ?? $line['name']).'|'.($line['unit'] ?? ''))
            ->map(function ($lines): array {
                $first = $lines->first();

                return [
                    ...$first,
                    'quantity' => round((float) $lines->sum('quantity'), 4),
                    'label' => $lines->pluck('label')->unique()->implode(', '),
                ];
            })
            ->values()
            ->all();
    }
}

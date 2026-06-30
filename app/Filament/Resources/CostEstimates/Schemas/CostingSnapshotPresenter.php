<?php

namespace App\Filament\Resources\CostEstimates\Schemas;

use App\Models\InventoryItem;
use App\Models\Machine;
use App\Support\Money;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Js;
use Illuminate\Support\Number;

class CostingSnapshotPresenter
{
    public static function moneyValue(string $value, bool $isPrimary = false): HtmlString
    {
        return new HtmlString(sprintf(
            '<span class="cost-summary-value%s">%s</span>',
            $isPrimary ? ' cost-summary-value-primary' : '',
            e($value),
        ));
    }

    public static function waitingValue(string $message = 'Awaiting inputs'): HtmlString
    {
        return new HtmlString(sprintf(
            '<span class="cost-summary-waiting">%s</span>',
            e($message),
        ));
    }

    public static function margin(float $marginPercent): HtmlString
    {
        return new HtmlString(sprintf(
            '<span class="cost-summary-value cost-summary-margin">%s</span>',
            e(Number::format($marginPercent, maxPrecision: 2).'%'),
        ));
    }

    /**
     * @param  array<int, array{label: string, name?: string|null, quantity: int|float|string, unit?: string|null, inventory?: array<string, mixed>}>  $materials
     */
    public static function materials(array $materials): HtmlString
    {
        if ($materials === []) {
            return self::emptyState('No inventory-backed material lines yet.');
        }

        return new HtmlString(sprintf(
            '<div class="cost-summary-list">%s</div>',
            collect($materials)
                ->map(fn (array $material): string => sprintf(
                    '<div class="cost-summary-list-row"><span class="cost-summary-material-name" tabindex="0" x-tooltip="{ content: %s, theme: $store.theme }">%s</span><strong>%s %s</strong></div>',
                    Js::from(self::materialTooltip($material))->toHtml(),
                    e($material['name'] ?? $material['label']),
                    e(Number::format((float) $material['quantity'], maxPrecision: 2)),
                    e($material['unit'] ?? ''),
                ))
                ->implode(''),
        ));
    }

    /**
     * @param  array<int, array{label: string, name?: string|null, quantity: int|float|string, unit?: string|null, machine?: array<string, mixed>, costing_speed?: int|float|string|null}>  $machines
     */
    public static function machines(array $machines): HtmlString
    {
        if ($machines === []) {
            return self::emptyState('No selected machines yet.');
        }

        return new HtmlString(sprintf(
            '<div class="cost-summary-list">%s</div>',
            collect($machines)
                ->map(fn (array $machine): string => sprintf(
                    '<div class="cost-summary-list-row"><span class="cost-summary-material-name" tabindex="0" x-tooltip="{ content: %s, theme: $store.theme }">%s</span><strong>%s %s</strong></div>',
                    Js::from(self::machineTooltip($machine))->toHtml(),
                    e($machine['name'] ?? $machine['label']),
                    e(Number::format((float) $machine['quantity'], maxPrecision: 2)),
                    e($machine['unit'] ?? ''),
                ))
                ->implode(''),
        ));
    }

    /**
     * @param  array{name?: string|null, quantity: int|float|string, unit?: string|null, inventory?: array<string, mixed>}  $material
     */
    private static function materialTooltip(array $material): string
    {
        $inventory = $material['inventory'] ?? [];
        $details = [];

        if (isset($inventory['stock_on_hand'])) {
            $details[] = Number::format((float) $inventory['stock_on_hand'], maxPrecision: 2).' '.($inventory['unit'] ?? $material['unit'] ?? '').' left';
        }

        if (! empty($inventory['gsm'])) {
            $details[] = Number::format((float) $inventory['gsm'], maxPrecision: 2).' gsm';
        }

        if (! empty($inventory['width']) || ! empty($inventory['height'])) {
            $details[] = trim(Number::format((float) ($inventory['width'] ?? 0), maxPrecision: 2).' x '.Number::format((float) ($inventory['height'] ?? 0), maxPrecision: 2).' cm');
        }

        return implode(' • ', $details) ?: 'Inventory item selected';
    }

    /**
     * @param  array{name?: string|null, quantity: int|float|string, unit?: string|null, machine?: array<string, mixed>, costing_speed?: int|float|string|null}  $machine
     */
    private static function machineTooltip(array $machine): string
    {
        $snapshot = $machine['machine'] ?? [];
        $details = [];

        if (isset($snapshot['operation_type'])) {
            $details[] = Machine::OPERATION_TYPES[$snapshot['operation_type']] ?? $snapshot['operation_type'];
        }

        if (filled($machine['costing_speed'] ?? null)) {
            $details[] = Number::format((float) $machine['costing_speed'], maxPrecision: 2).' costing units/hr';
        } elseif (isset($snapshot['production_speed'])) {
            $details[] = Number::format((float) $snapshot['production_speed'], maxPrecision: 2).' units/hr';
        }

        if (isset($snapshot['hourly_cost'])) {
            $details[] = Number::format((float) $snapshot['hourly_cost'], maxPrecision: 2).' '.Money::suffix().'/hr';
        }

        return implode(' | ', $details) ?: 'Machine selected';
    }

    /**
     * @param  array<int, string>  $warnings
     */
    public static function warnings(array $warnings): HtmlString
    {
        if ($warnings === []) {
            return new HtmlString('');
        }

        return new HtmlString(sprintf(
            '<div class="cost-summary-alert">%s</div>',
            collect($warnings)
                ->map(fn (string $warning): string => sprintf(
                    '<div class="cost-summary-alert-row cost-summary-alert-warning"><span>Rate fallback</span><strong>%s</strong></div>',
                    e($warning),
                ))
                ->implode(''),
        ));
    }

    public static function item(?InventoryItem $item, string $emptyMessage = 'Select an item to see cost, specs, and stock.'): HtmlString
    {
        if (! $item) {
            return self::emptyState($emptyMessage);
        }

        $purchaseCost = $item->hasPurchaseUnit()
            ? sprintf(
                '<div class="cost-snapshot-muted">%s %s/%s, %s %s per %s</div>',
                e(number_format($item->pricePerPurchaseUnit(), 2)),
                e(Money::suffix()),
                e($item->purchase_unit),
                e(number_format((float) $item->conversion_factor, 2)),
                e($item->unit),
                e($item->purchase_unit),
            )
            : '';

        return new HtmlString(sprintf(
            '<div class="cost-snapshot-card cost-snapshot-card-inventory">
                <div class="cost-snapshot-heading">%s</div>
                <div class="cost-snapshot-rate">%s %s/%s</div>
                %s
                <dl class="cost-snapshot-grid">
                    <div><dt>GSM</dt><dd>%s</dd></div>
                    <div><dt>Size (cm)</dt><dd>%s x %s</dd></div>
                    <div><dt>Stock</dt><dd>%s %s</dd></div>
                </dl>
            </div>',
            e($item->name),
            e(number_format($item->baseUnitCost(), 2)),
            e(Money::suffix()),
            e($item->unit),
            $purchaseCost,
            e($item->gsm ?: 'N/A'),
            e($item->width ?: 'N/A'),
            e($item->height ?: 'N/A'),
            e(number_format($item->stockOnHand(), 2)),
            e($item->unit),
        ));
    }

    public static function machine(?Machine $machine, string $emptyMessage = 'Select a machine to use speed, hourly cost, setup, and waste defaults.'): HtmlString
    {
        if (! $machine) {
            return self::emptyState($emptyMessage);
        }

        return new HtmlString(sprintf(
            '<div class="cost-snapshot-card cost-snapshot-card-machine">
                <div class="cost-snapshot-heading">%s</div>
                <dl class="cost-snapshot-grid">
                    <div><dt>Speed</dt><dd>%s units/hr</dd></div>
                    <div><dt>Hourly cost</dt><dd>%s %s</dd></div>
                    <div><dt>Operation</dt><dd>%s</dd></div>
                </dl>
            </div>',
            e($machine->name),
            e(number_format((float) $machine->production_speed, 2)),
            e(number_format((float) $machine->hourly_cost, 2)),
            e(Money::suffix()),
            e(Machine::OPERATION_TYPES[$machine->operation_type] ?? $machine->operation_type),
        ));
    }

    public static function emptyState(string $message): HtmlString
    {
        return new HtmlString(sprintf(
            '<span class="cost-snapshot-empty">%s</span>',
            e($message),
        ));
    }
}

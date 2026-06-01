<?php

namespace App\Filament\Resources\CostEstimates\Schemas;

use App\Models\InventoryItem;
use App\Models\Machine;
use Illuminate\Support\HtmlString;

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

    public static function waitingValue(string $message = 'Waiting for required fields'): HtmlString
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
            e(number_format($marginPercent, 2).'%'),
        ));
    }

    /**
     * @param  array<int, array{label: string, quantity: int|float|string, unit?: string|null}>  $materials
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
                    '<div class="cost-summary-list-row"><span>%s</span><strong>%s %s</strong></div>',
                    e($material['label']),
                    e(number_format((float) $material['quantity'], 2)),
                    e($material['unit'] ?? ''),
                ))
                ->implode(''),
        ));
    }

    /**
     * @param  array<int, string>  $warnings
     */
    public static function warnings(array $warnings): HtmlString
    {
        if ($warnings === []) {
            return new HtmlString('<span class="cost-summary-status cost-summary-status-ready">Ready to review</span>');
        }

        return new HtmlString(sprintf(
            '<div class="cost-summary-alert">%s</div>',
            collect($warnings)
                ->map(fn (string $warning): string => sprintf('<div>%s</div>', e($warning)))
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
                '<div class="cost-snapshot-muted">%s Birr/%s, %s %s per %s</div>',
                e(number_format($item->pricePerPurchaseUnit(), 2)),
                e($item->purchase_unit),
                e(number_format((float) $item->conversion_factor, 2)),
                e($item->unit),
                e($item->purchase_unit),
            )
            : '';

        return new HtmlString(sprintf(
            '<div class="cost-snapshot-card cost-snapshot-card-inventory">
                <div class="cost-snapshot-heading">%s</div>
                <div class="cost-snapshot-rate">%s Birr/%s</div>
                %s
                <dl class="cost-snapshot-grid">
                    <div><dt>GSM</dt><dd>%s</dd></div>
                    <div><dt>Size</dt><dd>%s x %s</dd></div>
                    <div><dt>Stock</dt><dd>%s %s</dd></div>
                </dl>
            </div>',
            e($item->name),
            e(number_format($item->baseUnitCost(), 2)),
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
                    <div><dt>Hourly cost</dt><dd>%s Birr</dd></div>
                    <div><dt>Operation</dt><dd>%s</dd></div>
                </dl>
            </div>',
            e($machine->name),
            e(number_format((float) $machine->production_speed, 2)),
            e(number_format((float) $machine->hourly_cost, 2)),
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

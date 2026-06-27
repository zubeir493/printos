<?php

namespace App\Filament\Warehouse\Widgets;

use App\Models\InventoryBalance;
use LaravelDaily\FilaWidgets\Data\BreakdownWidgetData;
use LaravelDaily\FilaWidgets\Widgets\BreakdownWidget;

class WipAvailabilityWidget extends BreakdownWidget
{
    protected static ?int $sort = 3;

    protected ?string $widgetLabel = 'WIP Availability';

    protected string $widgetFormat = 'number';

    protected int $widgetPrecision = 0;

    protected bool $showDelta = false;

    protected ?int $itemLimit = 6;

    protected bool $groupOther = true;

    protected function getData(): BreakdownWidgetData
    {
        return BreakdownWidgetData::fromCollection(
            InventoryBalance::query()
                ->join('inventory_items', 'inventory_balances.inventory_item_id', '=', 'inventory_items.id')
                ->join('warehouses', 'inventory_balances.warehouse_id', '=', 'warehouses.id')
                ->where('inventory_items.type', 'wip')
                ->where('inventory_balances.quantity_on_hand', '>', 0)
                ->selectRaw('CONCAT(inventory_items.name, " / ", warehouses.name) as stock_label, inventory_balances.quantity_on_hand')
                ->orderByDesc('inventory_balances.quantity_on_hand')
                ->limit(8)
                ->get(),
            labelKey: 'stock_label',
            valueKey: 'quantity_on_hand',
        );
    }
}

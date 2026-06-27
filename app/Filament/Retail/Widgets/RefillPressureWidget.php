<?php

namespace App\Filament\Retail\Widgets;

use App\Models\InventoryBalance;
use LaravelDaily\FilaWidgets\Data\BreakdownWidgetData;
use LaravelDaily\FilaWidgets\Widgets\BreakdownWidget;

class RefillPressureWidget extends BreakdownWidget
{
    protected static ?int $sort = 3;

    protected ?string $widgetLabel = 'Refill Pressure';

    protected string $widgetFormat = 'number';

    protected int $widgetPrecision = 0;

    protected bool $showDelta = false;

    protected ?int $itemLimit = 8;

    protected function getData(): BreakdownWidgetData
    {
        return BreakdownWidgetData::fromCollection(
            InventoryBalance::query()
                ->join('inventory_items', 'inventory_balances.inventory_item_id', '=', 'inventory_items.id')
                ->where('inventory_items.is_sellable', true)
                ->where('inventory_balances.quantity_on_hand', '<', 10)
                ->selectRaw('inventory_items.name as item_name, inventory_balances.quantity_on_hand')
                ->orderBy('inventory_balances.quantity_on_hand')
                ->limit(8)
                ->get(),
            labelKey: 'item_name',
            valueKey: 'quantity_on_hand',
        );
    }
}

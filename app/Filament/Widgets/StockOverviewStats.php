<?php

namespace App\Filament\Widgets;

use App\Models\InventoryBalance;
use App\Models\Warehouse;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StockOverviewStats extends BaseWidget
{
    public ?int $warehouse_id = null;

    protected function getStats(): array
    {
        $query = InventoryBalance::query();

        if ($this->warehouse_id) {
            $query->where('warehouse_id', $this->warehouse_id);
        }

        $totalItems = (clone $query)->where('quantity_on_hand', '>', 0)->count();
        $totalValue = (clone $query)->join('inventory_items', 'inventory_items.id', '=', 'inventory_balances.inventory_item_id')
            ->sum(\DB::raw('
                inventory_balances.quantity_on_hand * (
                    CASE
                        WHEN inventory_items.type IN ("tools", "spare_parts", "wip") THEN 0
                        WHEN inventory_items.type = "raw_material" AND inventory_items.conversion_factor > 0
                            THEN COALESCE(
                                NULLIF(CAST(inventory_items.average_cost AS DECIMAL(15,4)), 0),
                                CAST(inventory_items.price AS DECIMAL(15,4)) / CAST(inventory_items.conversion_factor AS DECIMAL(15,4))
                            )
                        ELSE COALESCE(
                            NULLIF(CAST(inventory_items.average_cost AS DECIMAL(15,4)), 0),
                            CAST(inventory_items.price AS DECIMAL(15,4))
                        )
                    END
                )
            '));

        $lowStockCount = (clone $query)->where('quantity_on_hand', '<', 10)->count();
        $outOfStockCount = (clone $query)->where('quantity_on_hand', '=', 0)->count();

        $warehouseName = $this->warehouse_id ? Warehouse::find($this->warehouse_id)?->name : 'All Warehouses';

        // Sample chart data
        $itemsChart = [100, 120, 110, 130, 125, 140, $totalItems];
        $valueChart = [50000, 55000, 52000, 60000, 58000, 62000, $totalValue];

        return [
            Stat::make('Total SKU count ('.$warehouseName.')', $totalItems)
                ->description('Active items with stock')
                ->descriptionIcon('heroicon-m-cube')
                ->color('info')
                ->chart($itemsChart),
            Stat::make('Warehouse Valuation', Money::abbreviate($totalValue, precision: 2))
                ->description('Total value of stock on hand')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->chart($valueChart),
            Stat::make('Low Stock Items', $lowStockCount)
                ->description('Items below 10 units')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($lowStockCount > 0 ? 'warning' : 'success'),
            Stat::make('Out of Stock', $outOfStockCount)
                ->description('Items with zero quantity')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color($outOfStockCount > 0 ? 'danger' : 'success'),
        ];
    }
}

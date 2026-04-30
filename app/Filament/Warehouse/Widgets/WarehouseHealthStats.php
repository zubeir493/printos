<?php

namespace App\Filament\Warehouse\Widgets;

use App\Models\Dispatch;
use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class WarehouseHealthStats extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        // 1. Dead Stock Risk
        // Items with positive balance but NO movements in 90 days
        $deadStockCount = InventoryItem::whereHas('inventoryBalances', function ($q) {
            $q->where('quantity_on_hand', '>', 0);
        })
            ->whereDoesntHave('stockMovements', function ($q) {
                $q->where('movement_date', '>=', now()->subDays(90));
            })
            ->count();

        // 2. Dispatch Throughput Today
        $dispatchesToday = Dispatch::whereDate('created_at', Carbon::today())->count();

        // 3. Low Stock Items
        $lowStockCount = InventoryBalance::query()
            ->select('inventory_item_id')
            ->groupBy('inventory_item_id')
            ->havingRaw('SUM(quantity_on_hand) < 10')
            ->count();

        return [
            Stat::make('Dead Stock Risk', $deadStockCount.' Items')
                ->description('No movement in 90+ days')
                ->descriptionIcon('heroicon-m-exclamation-circle')
                ->color($deadStockCount > 10 ? 'danger' : 'success'),

            Stat::make('Outbound Today', $dispatchesToday)
                ->description('Dispatches leaving today')
                ->descriptionIcon('heroicon-m-truck')
                ->color('primary'),

            Stat::make('Low Stock Alerts', $lowStockCount)
                ->description('Items nearing depletion')
                ->color($lowStockCount > 0 ? 'warning' : 'success'),
        ];
    }
}

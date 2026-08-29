<?php

namespace App\Filament\Retail\Widgets;

use App\Models\InventoryBalance;
use App\Models\SalesOrder;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RetailStockHealthStats extends BaseWidget
{
    protected static ?int $sort = 3;

    protected function getStats(): array
    {
        $lowSellable = InventoryBalance::query()
            ->where('quantity_on_hand', '>', 0)
            ->where('quantity_on_hand', '<', 10)
            ->whereHas('inventoryItem', fn ($query) => $query->where('is_sellable', true))
            ->count();

        $unpaidRetailOrders = SalesOrder::query()
            ->where('payment_mode', 'cash')
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->count();

        return [
            Stat::make('Low Counter Stock', $lowSellable)
                ->description('Sellable items below 10 units')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($lowSellable > 0 ? 'warning' : 'success'),
            Stat::make('Open Retail Tickets', $unpaidRetailOrders)
                ->description('Paid-now orders not yet closed')
                ->descriptionIcon('heroicon-m-clock')
                ->color($unpaidRetailOrders > 0 ? 'warning' : 'success'),
            Stat::make('Avg Ticket Size', Money::abbreviate(SalesOrder::query()
                ->where('payment_mode', 'cash')
                ->where('order_date', '>=', now()->subDays(30))
                ->avg('total'), precision: 2))
                ->description('Paid-now sales, last 30 days')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('primary'),
        ];
    }
}

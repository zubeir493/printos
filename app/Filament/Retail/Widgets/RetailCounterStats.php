<?php

namespace App\Filament\Retail\Widgets;

use App\Models\InventoryBalance;
use App\Models\SalesOrder;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RetailCounterStats extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $paidNowSales = (float) SalesOrder::query()
            ->where('payment_mode', 'cash')
            ->whereDate('order_date', today())
            ->sum('total');

        $tickets = SalesOrder::query()
            ->whereDate('order_date', today())
            ->count();

        $lowSellable = InventoryBalance::query()
            ->where('quantity_on_hand', '>', 0)
            ->where('quantity_on_hand', '<', 10)
            ->whereHas('inventoryItem', fn ($query) => $query->where('is_sellable', true))
            ->count();
        $averageTicket = (float) SalesOrder::query()
            ->where('payment_mode', 'cash')
            ->where('order_date', '>=', now()->subDays(30))
            ->avg('total');

        return [
            Stat::make('Counter Sales Today', Money::abbreviate($paidNowSales, precision: 2))
                ->description('Paid-now sales booked today')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($paidNowSales > 0 ? 'success' : 'gray'),
            Stat::make('Tickets Today', $tickets)
                ->description('Retail orders processed')
                ->descriptionIcon('heroicon-m-receipt-percent')
                ->color('info'),
            Stat::make('Low Counter Stock', $lowSellable)
                ->description('Sellable items below 10 units')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($lowSellable > 0 ? 'warning' : 'success'),
            Stat::make('Avg Ticket Size', Money::abbreviate($averageTicket, precision: 2))
                ->description('Paid-now sales, last 30 days')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('primary'),
        ];
    }
}

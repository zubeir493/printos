<?php

namespace App\Filament\Sales\Widgets;

use App\Models\SalesOrder;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SalesPanelStats extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $todayRevenue = (float) SalesOrder::query()
            ->whereDate('order_date', today())
            ->sum('total');

        $openQuotes = SalesOrder::query()
            ->whereIn('status', ['draft', 'pending'])
            ->count();

        $overdueOrders = SalesOrder::query()
            ->whereDate('due_date', '<', today())
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->count();

        return [
            Stat::make('Today\'s Bookings', Money::abbreviate($todayRevenue, precision: 2))
                ->description('Sales order value booked today')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($todayRevenue > 0 ? 'success' : 'gray')
                ->chart($this->dailyOrderTotals()),
            Stat::make('Open Pipeline', $openQuotes)
                ->description('Draft or pending sales orders')
                ->descriptionIcon('heroicon-m-funnel')
                ->color($openQuotes > 0 ? 'warning' : 'success'),
            Stat::make('Overdue Commitments', $overdueOrders)
                ->description('Orders past due and not closed')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($overdueOrders > 0 ? 'danger' : 'success'),
        ];
    }

    private function dailyOrderTotals(): array
    {
        return collect(range(6, 0))
            ->map(fn (int $daysAgo) => (float) SalesOrder::query()
                ->whereDate('order_date', today()->subDays($daysAgo))
                ->sum('total'))
            ->all();
    }
}

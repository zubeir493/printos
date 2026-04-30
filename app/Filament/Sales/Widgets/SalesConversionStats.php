<?php

namespace App\Filament\Sales\Widgets;

use App\Models\Partner;
use App\Models\SalesOrder;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SalesConversionStats extends BaseWidget
{
    protected static ?int $sort = 3;

    protected function getStats(): array
    {
        $orders = SalesOrder::query()->count();
        $completed = SalesOrder::query()->where('status', 'completed')->count();
        $conversion = $orders > 0 ? ($completed / $orders) * 100 : 0;

        $newCustomers = Partner::query()
            ->where('is_customer', true)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        return [
            Stat::make('Win Rate', round($conversion, 1).'%')
                ->description('Completed orders vs total orders')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color($conversion >= 50 ? 'success' : 'warning'),
            Stat::make('New Customers', $newCustomers)
                ->description('Customers created in the last 30 days')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('info'),
            Stat::make('Average Order Value', number_format((float) SalesOrder::query()->avg('total'), 2))
                ->description('Across all sales orders')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('primary'),
        ];
    }
}

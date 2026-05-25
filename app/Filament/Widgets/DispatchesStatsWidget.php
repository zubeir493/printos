<?php

namespace App\Filament\Widgets;

use App\Models\Dispatch;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DispatchesStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalDispatches = Dispatch::count();
        $pendingDispatches = Dispatch::whereNull('delivery_date')->count();
        $deliveredToday = Dispatch::whereDate('delivery_date', today())->count();
        $lateDispatches = Dispatch::whereNull('delivery_date')
            ->where('created_at', '<', now()->subDays(3))
            ->count();

        // Sample chart data
        $pendingChart = [10, 12, 8, 15, 11, 9, $pendingDispatches];
        $deliveredChart = [5, 7, 6, 8, 9, 10, $deliveredToday];
        $lateChart = [2, 1, 3, 0, 1, 2, $lateDispatches];

        return [
            Stat::make('Total Dispatches', $totalDispatches)
                ->description('All time dispatches')
                ->descriptionIcon('heroicon-m-truck')
                ->color('info'),
            Stat::make('Pending Dispatches', $pendingDispatches)
                ->description('Awaiting delivery')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingDispatches > 10 ? 'warning' : 'success')
                ->chart($pendingChart),
            Stat::make('Delivered Today', $deliveredToday)
                ->description('Completed deliveries')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->chart($deliveredChart),
            Stat::make('Late Dispatches', $lateDispatches)
                ->description('Over 3 days old')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($lateDispatches > 0 ? 'danger' : 'success')
                ->chart($lateChart),
        ];
    }
}

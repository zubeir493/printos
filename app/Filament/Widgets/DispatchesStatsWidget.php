<?php

namespace App\Filament\Widgets;

use App\Models\Dispatch;
use App\Support\WarehouseAccess;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DispatchesStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $dispatches = WarehouseAccess::scope(Dispatch::query(), 'dispatches.warehouse_id');
        $totalDispatches = (clone $dispatches)->count();
        $pendingDispatches = (clone $dispatches)->whereNull('delivery_date')->count();
        $deliveredToday = (clone $dispatches)->whereDate('delivery_date', today())->count();
        $lateDispatches = (clone $dispatches)->whereNull('delivery_date')
            ->where('created_at', '<', now()->subDays(3))
            ->count();

        return [
            Stat::make('Total Dispatches', $totalDispatches)
                ->description('All time dispatches')
                ->descriptionIcon('heroicon-m-truck')
                ->color('info'),
            Stat::make('Pending Dispatches', $pendingDispatches)
                ->description('Awaiting delivery')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingDispatches > 10 ? 'warning' : 'success'),
            Stat::make('Delivered Today', $deliveredToday)
                ->description('Completed deliveries')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
            Stat::make('Late Dispatches', $lateDispatches)
                ->description('Over 3 days old')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($lateDispatches > 0 ? 'danger' : 'success'),
        ];
    }
}

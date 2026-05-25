<?php

namespace App\Filament\Operations\Widgets;

use App\Models\Dispatch;
use App\Models\JobOrder;
use App\Models\PurchaseOrder;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OperationsHealthStats extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $activeJobOrders = JobOrder::whereIn('status', ['planned', 'in_production'])->count();
        $pendingPurchases = PurchaseOrder::whereIn('status', ['draft', 'approved'])->count();
        $dispatchesToday = Dispatch::whereDate('created_at', Carbon::today())->count();

        return [
            Stat::make('Active Job Orders', $activeJobOrders)
                ->description('In pipeline')
                ->descriptionIcon('heroicon-m-wrench')
                ->color('primary'),

            Stat::make('Pending Purchases', $pendingPurchases)
                ->description('Draft or approved purchase orders')
                ->color($pendingPurchases > 10 ? 'warning' : 'success'),

            Stat::make('Dispatches Today', $dispatchesToday)
                ->description('Outbound shipments')
                ->descriptionIcon('heroicon-m-truck')
                ->color('success'),
        ];
    }
}

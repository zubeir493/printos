<?php

namespace App\Filament\Operations\Widgets;

use App\Models\Dispatch;
use App\Models\JobOrder;
use App\Models\JobOrderTask;
use App\Models\PurchaseOrder;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OperationsPanelStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $activeJobOrders = JobOrder::query()
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->count();

        $unplannedTasks = JobOrderTask::query()
            ->whereDoesntHave('productionPlanItems')
            ->count();
        $pendingPurchases = PurchaseOrder::query()
            ->whereIn('status', ['draft', 'approved'])
            ->count();
        $dispatchesToday = Dispatch::query()
            ->whereDate('created_at', today())
            ->count();

        return [
            Stat::make('Active Job Orders', $activeJobOrders)
                ->description('Orders not completed or cancelled')
                ->color('primary'),
            Stat::make('Production Planning Coverage', $unplannedTasks)
                ->description('Tasks still missing a production plan')
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color($unplannedTasks > 0 ? 'warning' : 'success'),
            Stat::make('Pending Purchases', $pendingPurchases)
                ->description('Draft or approved purchase orders')
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->color($pendingPurchases > 0 ? 'warning' : 'success'),
            Stat::make('Dispatches Today', $dispatchesToday)
                ->description('Outbound records created today')
                ->descriptionIcon('heroicon-m-truck')
                ->color('success'),
        ];
    }
}

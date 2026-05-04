<?php

namespace App\Filament\Widgets;

use App\Models\Dispatch;
use App\Models\StockAdjustment;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AdminHealthStats extends BaseWidget
{
    protected static ?int $sort = 1;
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $cashConversionExpression = match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => 'AVG(TIMESTAMPDIFF(DAY, sales_orders.created_at, payment_allocations.created_at)) as avg_days',
            'pgsql' => 'AVG(EXTRACT(DAY FROM payment_allocations.created_at - sales_orders.created_at)) as avg_days',
            default => 'AVG(JULIANDAY(payment_allocations.created_at) - JULIANDAY(sales_orders.created_at)) as avg_days',
        };

        // 1. Avg Cash Conversion Cycle (Approx: Order Date to Payment Date)
        // Calculating average days between SalesOrder created_at and its latest PaymentAllocation
        $cashConversionDays = \App\Models\SalesOrder::where('status', 'completed')
            ->join('payment_allocations', function ($join) {
                $join->on('sales_orders.id', '=', 'payment_allocations.allocatable_id')
                    ->where('payment_allocations.allocatable_type', '=', \App\Models\SalesOrder::class);
            })
            ->selectRaw($cashConversionExpression)
            ->value('avg_days') ?? 0;

        // 2. Dispatch Health: Count of Pending Dispatches older than 3 days
        $lateDispatches = \App\Models\Dispatch::whereNull('delivery_date')
            ->where('created_at', '<', now()->subDays(3))
            ->count();

        // 3. Shrinkage (Negative Stock Adjustments) - Last 30 Days
        $shrinkageValue = \App\Models\StockAdjustmentItem::whereHas('stockAdjustment', function ($query) {
                $query->where('created_at', '>=', now()->subDays(30));
            })
            ->where('adjustment_quantity', '<', 0)
            ->sum('adjustment_quantity');
        
        $shrinkageValue = abs($shrinkageValue);

        // Sample chart data for demonstration
        $cashChart = [10, 12, 8, 15, 11, 9, round($cashConversionDays, 1)];
        $dispatchChart = [5, 3, 7, 2, 4, 1, $lateDispatches];
        $shrinkageChart = [50, 40, 60, 30, 45, 55, $shrinkageValue];

        return [
            Stat::make('Cash Conversion Cycle', round($cashConversionDays, 1) . ' Days')
                ->description('Avg days from Order to Payment')
                ->descriptionIcon('heroicon-m-clock')
                ->color($cashConversionDays > 14 ? 'warning' : 'success')
                ->chart($cashChart),
            
            Stat::make('Delayed Dispatches', $lateDispatches)
                ->description('Unshipped orders > 3 days old')
                ->descriptionIcon('heroicon-m-truck')
                ->color($lateDispatches > 0 ? 'danger' : 'success')
                ->chart($dispatchChart),
                
            Stat::make('Inventory Shrinkage (30d)', number_format($shrinkageValue) . ' Units')
                ->description('Loss from manual adjustments')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($shrinkageValue > 100 ? 'danger' : 'success')
                ->chart($shrinkageChart),
        ];
    }
}

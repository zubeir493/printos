<?php

namespace App\Filament\Widgets;

use App\Models\Dispatch;
use App\Models\SalesOrder;
use App\Models\StockAdjustmentItem;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class AdminHealthStats extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $cashConversionExpression = match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => 'AVG(TIMESTAMPDIFF(DAY, sales_orders.created_at, payments.created_at)) as avg_days',
            'pgsql' => 'AVG(EXTRACT(DAY FROM payments.created_at - sales_orders.created_at)) as avg_days',
            default => 'AVG(JULIANDAY(payments.created_at) - JULIANDAY(sales_orders.created_at)) as avg_days',
        };

        // 1. Avg Cash Conversion Cycle (Approx: Order Date to Payment Date)
        // Calculating average days between SalesOrder created_at and its direct payment.
        $cashConversionDays = SalesOrder::where('status', 'completed')
            ->join('payments', function ($join) {
                $join->on('sales_orders.id', '=', 'payments.payable_id')
                    ->where('payments.payable_type', '=', SalesOrder::class)
                    ->whereNull('payments.voided_at');
            })
            ->selectRaw($cashConversionExpression)
            ->value('avg_days') ?? 0;

        // 2. Dispatch Health: Count of Pending Dispatches older than 3 days
        $lateDispatches = Dispatch::whereNull('delivery_date')
            ->where('created_at', '<', now()->subDays(3))
            ->count();

        // 3. Shrinkage (Negative Stock Adjustments) - Last 30 Days
        $shrinkageUnits = abs((float) StockAdjustmentItem::whereHas('stockAdjustment', function ($query) {
            $query->where('created_at', '>=', now()->subDays(30));
        })
            ->where('adjustment_quantity', '<', 0)
            ->sum('adjustment_quantity'));

        return [
            Stat::make('Cash Conversion Cycle', round($cashConversionDays, 1).' Days')
                ->description('Avg days from Order to Payment')
                ->descriptionIcon('heroicon-m-clock')
                ->color($cashConversionDays > 14 ? 'warning' : 'success'),

            Stat::make('Delayed Dispatches', $lateDispatches)
                ->description('Unshipped orders > 3 days old')
                ->descriptionIcon('heroicon-m-truck')
                ->color($lateDispatches > 0 ? 'danger' : 'success'),

            Stat::make('Inventory Shrinkage (30d)', Money::abbreviate($shrinkageUnits, precision: 2).' Units')
                ->description('Loss from manual adjustments')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($shrinkageUnits > 100 ? 'danger' : 'success'),
        ];
    }
}

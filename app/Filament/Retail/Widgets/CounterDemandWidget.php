<?php

namespace App\Filament\Retail\Widgets;

use App\Filament\Widgets\Concerns\HasFilaWidgetMetrics;
use App\Models\SalesOrder;
use LaravelDaily\FilaWidgets\Data\SparklineTableRowData;
use LaravelDaily\FilaWidgets\Data\SparklineTableWidgetData;
use LaravelDaily\FilaWidgets\Widgets\SparklineTableWidget;

class CounterDemandWidget extends SparklineTableWidget
{
    use HasFilaWidgetMetrics;

    protected static ?int $sort = 2;

    protected ?string $widgetLabel = 'Counter Demand';

    protected function getData(): SparklineTableWidgetData
    {
        $periods = $this->comparisonPeriods();
        $paidNowSales = SalesOrder::query()->where('payment_mode', 'cash');
        $currentOrders = $this->countDuring($paidNowSales, $periods['current'], 'order_date');
        $previousOrders = $this->countDuring($paidNowSales, $periods['previous'], 'order_date');
        $currentSales = $this->sumDuring($paidNowSales, $periods['current'], 'total', 'order_date');
        $previousSales = $this->sumDuring($paidNowSales, $periods['previous'], 'total', 'order_date');

        return SparklineTableWidgetData::fromRows(
            new SparklineTableRowData('Paid-now sales', $currentSales, $previousSales, $this->sparkline($paidNowSales, 'SUM(total)', 'order_date')),
            new SparklineTableRowData('Orders', $currentOrders, $previousOrders, $this->sparkline($paidNowSales, 'COUNT(*)', 'order_date', 0), 'number', 0),
            new SparklineTableRowData('Average order', $currentOrders > 0 ? $currentSales / $currentOrders : 0, $previousOrders > 0 ? $previousSales / $previousOrders : 0, $this->sparkline($paidNowSales, 'AVG(total)', 'order_date'), color: 'success'),
        );
    }
}

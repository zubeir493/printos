<?php

namespace App\Filament\Sales\Widgets;

use App\Filament\Widgets\Concerns\HasFilaWidgetMetrics;
use App\Models\CostEstimate;
use App\Models\SalesOrder;
use LaravelDaily\FilaWidgets\Data\SparklineTableRowData;
use LaravelDaily\FilaWidgets\Data\SparklineTableWidgetData;
use LaravelDaily\FilaWidgets\Widgets\SparklineTableWidget;

class SalesMomentumWidget extends SparklineTableWidget
{
    use HasFilaWidgetMetrics;

    protected static ?int $sort = 2;

    protected ?string $widgetLabel = 'Sales Momentum';

    protected function getData(): SparklineTableWidgetData
    {
        $periods = $this->comparisonPeriods();
        $orders = SalesOrder::query();
        $paidNowSales = SalesOrder::query()->where('payment_mode', 'cash');
        $estimates = CostEstimate::query();

        return SparklineTableWidgetData::fromRows(
            new SparklineTableRowData('Orders', $this->countDuring($orders, $periods['current']), $this->countDuring($orders, $periods['previous']), $this->sparkline($orders, 'COUNT(*)', precision: 0), 'number', 0),
            new SparklineTableRowData('Paid-now sales', $this->sumDuring($paidNowSales, $periods['current'], 'total'), $this->sumDuring($paidNowSales, $periods['previous'], 'total'), $this->sparkline($paidNowSales, 'SUM(total)')),
            new SparklineTableRowData('Estimate value', $this->sumDuring($estimates, $periods['current'], 'total'), $this->sumDuring($estimates, $periods['previous'], 'total'), $this->sparkline($estimates, 'SUM(total)')),
        );
    }
}

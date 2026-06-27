<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasFilaWidgetMetrics;
use App\Models\JobOrder;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use LaravelDaily\FilaWidgets\Data\SparklineTableRowData;
use LaravelDaily\FilaWidgets\Data\SparklineTableWidgetData;
use LaravelDaily\FilaWidgets\Widgets\SparklineTableWidget;

class ExecutivePulseWidget extends SparklineTableWidget
{
    use HasFilaWidgetMetrics;

    protected static ?int $sort = 2;

    protected ?string $widgetLabel = 'Executive Pulse';

    protected string $widgetCurrency = 'ETB';

    protected function getData(): SparklineTableWidgetData
    {
        $periods = $this->comparisonPeriods();
        $revenue = SalesOrder::query()->where('status', SalesOrder::STATUS_COMPLETED);
        $pipeline = JobOrder::query()->where('status', 'active');
        $purchases = PurchaseOrder::query()->whereIn('status', ['draft', 'approved']);

        return SparklineTableWidgetData::fromRows(
            new SparklineTableRowData('Revenue', $this->sumDuring($revenue, $periods['current'], 'total'), $this->sumDuring($revenue, $periods['previous'], 'total'), $this->sparkline($revenue, 'SUM(total)')),
            new SparklineTableRowData('Pipeline value', $this->sumDuring($pipeline, $periods['current'], 'total'), $this->sumDuring($pipeline, $periods['previous'], 'total'), $this->sparkline($pipeline, 'SUM(total)')),
            new SparklineTableRowData('Open purchases', $this->sumDuring($purchases, $periods['current'], 'total'), $this->sumDuring($purchases, $periods['previous'], 'total'), $this->sparkline($purchases, 'SUM(total)'), color: 'warning'),
        );
    }
}

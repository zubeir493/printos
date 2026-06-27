<?php

namespace App\Filament\Production\Widgets;

use App\Filament\Widgets\Concerns\HasFilaWidgetMetrics;
use App\Models\ProductionPlanItem;
use App\Models\ProductionReportItem;
use LaravelDaily\FilaWidgets\Data\SparklineTableRowData;
use LaravelDaily\FilaWidgets\Data\SparklineTableWidgetData;
use LaravelDaily\FilaWidgets\Widgets\SparklineTableWidget;

class MachineOutputPaceWidget extends SparklineTableWidget
{
    use HasFilaWidgetMetrics;

    protected static ?int $sort = 2;

    protected ?string $widgetLabel = 'Machine Output Pace';

    protected string $widgetFormat = 'number';

    protected int $widgetPrecision = 0;

    protected function getData(): SparklineTableWidgetData
    {
        $periods = $this->comparisonPeriods();
        $planned = ProductionPlanItem::query();
        $actual = ProductionReportItem::query();
        $plannedCurrent = $this->sumDuring($planned, $periods['current'], 'planned_quantity');
        $actualCurrent = $this->sumDuring($actual, $periods['current'], 'actual_quantity', 'date');
        $plannedPrevious = $this->sumDuring($planned, $periods['previous'], 'planned_quantity');
        $actualPrevious = $this->sumDuring($actual, $periods['previous'], 'actual_quantity', 'date');

        return SparklineTableWidgetData::fromRows(
            new SparklineTableRowData('Planned quantity', $plannedCurrent, $plannedPrevious, $this->sparkline($planned, 'SUM(planned_quantity)', precision: 0), 'number', 0),
            new SparklineTableRowData('Actual quantity', $actualCurrent, $actualPrevious, $this->sparkline($actual, 'SUM(actual_quantity)', 'date', 0), 'number', 0, color: 'success'),
            new SparklineTableRowData('Variance', $actualCurrent - $plannedCurrent, $actualPrevious - $plannedPrevious, showSparkline: false, format: 'number', precision: 0, color: $actualCurrent >= $plannedCurrent ? 'success' : 'warning'),
        );
    }
}

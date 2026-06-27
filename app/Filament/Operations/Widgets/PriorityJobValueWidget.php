<?php

namespace App\Filament\Operations\Widgets;

use App\Filament\Widgets\Concerns\HasFilaWidgetMetrics;
use App\Models\JobOrder;
use LaravelDaily\FilaWidgets\Data\SparklineTableRowData;
use LaravelDaily\FilaWidgets\Data\SparklineTableWidgetData;
use LaravelDaily\FilaWidgets\Widgets\SparklineTableWidget;

class PriorityJobValueWidget extends SparklineTableWidget
{
    use HasFilaWidgetMetrics;

    protected static ?int $sort = 3;

    protected ?string $widgetLabel = 'Priority Job Value';

    protected string $widgetCurrency = 'ETB';

    protected function getData(): SparklineTableWidgetData
    {
        $periods = $this->comparisonPeriods();
        $priority = JobOrder::query()
            ->where('advance_paid', true)
            ->whereNotIn('status', ['completed', 'cancelled']);
        $overdue = (clone $priority)->whereDate('due_date', '<', today());

        return SparklineTableWidgetData::fromRows(
            new SparklineTableRowData('Priority jobs', $this->countDuring($priority, $periods['current']), $this->countDuring($priority, $periods['previous']), $this->sparkline($priority, 'COUNT(*)', precision: 0), 'number', 0),
            new SparklineTableRowData('Priority value', $this->sumDuring($priority, $periods['current'], 'total'), $this->sumDuring($priority, $periods['previous'], 'total'), $this->sparkline($priority, 'SUM(total)')),
            new SparklineTableRowData('Overdue priority', $this->countDuring($overdue, $periods['current']), $this->countDuring($overdue, $periods['previous']), $this->sparkline($overdue, 'COUNT(*)', precision: 0), 'number', 0, color: 'danger'),
        );
    }
}

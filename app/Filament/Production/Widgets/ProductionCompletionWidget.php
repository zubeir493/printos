<?php

namespace App\Filament\Production\Widgets;

use App\Filament\Widgets\Concerns\HasFilaWidgetMetrics;
use App\Models\ProductionPlanItem;
use App\Models\ProductionReportItem;
use LaravelDaily\FilaWidgets\Data\CompletionRateWidgetData;
use LaravelDaily\FilaWidgets\Widgets\CompletionRateWidget;

class ProductionCompletionWidget extends CompletionRateWidget
{
    use HasFilaWidgetMetrics;

    protected static ?int $sort = 4;

    protected ?string $widgetLabel = 'Production Completion';

    protected function getData(): CompletionRateWidgetData
    {
        $period = $this->currentPeriod();
        $planned = $this->sumDuring(ProductionPlanItem::query(), $period, 'planned_quantity');
        $actual = $this->sumDuring(ProductionReportItem::query(), $period, 'actual_quantity', 'date');

        if ($planned <= 0) {
            return new CompletionRateWidgetData(0, isEmpty: true);
        }

        return new CompletionRateWidgetData(
            value: round(min(($actual / $planned) * 100, 100), 1),
            description: number_format($actual).' of '.number_format($planned).' planned quantity reported',
        );
    }

    protected function getThresholds(): array
    {
        return [
            ['threshold' => 50, 'color' => 'danger', 'label' => 'Behind'],
            ['threshold' => 85, 'color' => 'warning', 'label' => 'In progress'],
            ['threshold' => 100, 'color' => 'success', 'label' => 'On pace'],
        ];
    }
}

<?php

namespace App\Filament\Sales\Widgets;

use App\Models\SalesOrder;
use LaravelDaily\FilaWidgets\Data\CompletionRateWidgetData;
use LaravelDaily\FilaWidgets\Widgets\CompletionRateWidget;

class SalesCompletionRateWidget extends CompletionRateWidget
{
    protected static ?int $sort = 4;

    protected ?string $widgetLabel = 'Sales Completion Rate';

    protected function getData(): CompletionRateWidgetData
    {
        $total = SalesOrder::query()->where('status', '!=', SalesOrder::STATUS_VOID)->count();
        $completed = SalesOrder::query()->where('status', SalesOrder::STATUS_COMPLETED)->count();

        if ($total === 0) {
            return new CompletionRateWidgetData(0, isEmpty: true);
        }

        return new CompletionRateWidgetData(
            value: round(($completed / $total) * 100, 1),
            description: "{$completed} of {$total} orders completed",
        );
    }

    protected function getThresholds(): array
    {
        return [
            ['threshold' => 50, 'color' => 'danger', 'label' => 'Low'],
            ['threshold' => 75, 'color' => 'warning', 'label' => 'Watch'],
            ['threshold' => 100, 'color' => 'success', 'label' => 'Healthy'],
        ];
    }
}

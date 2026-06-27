<?php

namespace App\Filament\Finance\Widgets;

use App\Filament\Widgets\Concerns\HasFilaWidgetMetrics;
use App\Models\Payment;
use LaravelDaily\FilaWidgets\Data\HeatmapCalendarWidgetData;
use LaravelDaily\FilaWidgets\Widgets\HeatmapCalendarWidget;

class CashflowActivityWidget extends HeatmapCalendarWidget
{
    use HasFilaWidgetMetrics;

    protected static ?int $sort = 5;

    protected ?string $widgetLabel = 'Cashflow Activity';

    protected string $widgetCurrency = 'ETB';

    protected function getData(): HeatmapCalendarWidgetData
    {
        return new HeatmapCalendarWidgetData(
            entries: $this->heatmap(
                Payment::query()->whereNull('voided_at'),
                'SUM(amount)',
                'payment_date',
            ),
            description: 'Daily inbound and outbound payment activity',
        );
    }
}

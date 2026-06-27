<?php

namespace App\Filament\Warehouse\Widgets;

use App\Filament\Widgets\Concerns\HasFilaWidgetMetrics;
use App\Models\Dispatch;
use App\Models\GoodsReceipt;
use LaravelDaily\FilaWidgets\Data\HeatmapCalendarWidgetData;
use LaravelDaily\FilaWidgets\Widgets\HeatmapCalendarWidget;

class LogisticsActivityWidget extends HeatmapCalendarWidget
{
    use HasFilaWidgetMetrics;

    protected static ?int $sort = 2;

    protected ?string $widgetLabel = 'Logistics Activity';

    protected string $widgetFormat = 'number';

    protected int $widgetPrecision = 0;

    protected function getData(): HeatmapCalendarWidgetData
    {
        $entries = collect($this->heatmap(GoodsReceipt::query(), dateColumn: 'receipt_date', precision: 0))
            ->mergeRecursive($this->heatmap(Dispatch::query(), dateColumn: 'delivery_date', precision: 0))
            ->map(fn ($value): float => (float) collect($value)->sum())
            ->all();

        return new HeatmapCalendarWidgetData($entries, 'Goods receipts and dispatches by day');
    }
}

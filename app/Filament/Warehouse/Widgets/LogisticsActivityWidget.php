<?php

namespace App\Filament\Warehouse\Widgets;

use App\Filament\Widgets\Concerns\HasFilaWidgetMetrics;
use App\Models\Dispatch;
use App\Models\GoodsReceipt;
use App\Support\WarehouseAccess;
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
        $receipts = WarehouseAccess::scope(GoodsReceipt::query(), 'goods_receipts.warehouse_id');
        $dispatches = WarehouseAccess::scope(Dispatch::query(), 'dispatches.warehouse_id');
        $entries = collect($this->heatmap($receipts, dateColumn: 'receipt_date', precision: 0))
            ->mergeRecursive($this->heatmap($dispatches, dateColumn: 'delivery_date', precision: 0))
            ->map(fn ($value): float => (float) collect($value)->sum())
            ->all();

        return new HeatmapCalendarWidgetData($entries, 'Goods receipts and dispatches by day');
    }
}

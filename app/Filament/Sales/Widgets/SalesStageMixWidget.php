<?php

namespace App\Filament\Sales\Widgets;

use App\Models\SalesOrder;
use LaravelDaily\FilaWidgets\Data\BreakdownWidgetData;
use LaravelDaily\FilaWidgets\Widgets\BreakdownWidget;

class SalesStageMixWidget extends BreakdownWidget
{
    protected static ?int $sort = 3;

    protected ?string $widgetLabel = 'Sales Stage Mix';

    protected string $widgetFormat = 'number';

    protected int $widgetPrecision = 0;

    protected bool $showDelta = false;

    protected function getData(): BreakdownWidgetData
    {
        return BreakdownWidgetData::fromCollection(
            SalesOrder::query()
                ->select('status')
                ->selectRaw('COUNT(*) as aggregate')
                ->groupBy('status')
                ->orderByDesc('aggregate')
                ->get(),
            labelKey: fn ($item): string => str($item->status)->headline()->toString(),
            valueKey: 'aggregate',
        );
    }
}

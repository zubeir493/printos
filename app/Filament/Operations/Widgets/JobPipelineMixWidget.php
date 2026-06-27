<?php

namespace App\Filament\Operations\Widgets;

use App\Models\JobOrder;
use LaravelDaily\FilaWidgets\Data\BreakdownWidgetData;
use LaravelDaily\FilaWidgets\Widgets\BreakdownWidget;

class JobPipelineMixWidget extends BreakdownWidget
{
    protected static ?int $sort = 2;

    protected ?string $widgetLabel = 'Job Pipeline Mix';

    protected string $widgetFormat = 'number';

    protected int $widgetPrecision = 0;

    protected bool $showDelta = false;

    protected function getData(): BreakdownWidgetData
    {
        return BreakdownWidgetData::fromCollection(
            JobOrder::query()
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

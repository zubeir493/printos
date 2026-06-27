<?php

namespace App\Filament\Design\Widgets;

use App\Models\Artwork;
use App\Models\JobOrderTask;
use LaravelDaily\FilaWidgets\Data\BreakdownItemData;
use LaravelDaily\FilaWidgets\Data\BreakdownWidgetData;
use LaravelDaily\FilaWidgets\Widgets\BreakdownWidget;

class ArtworkPipelineWidget extends BreakdownWidget
{
    protected static ?int $sort = 2;

    protected ?string $widgetLabel = 'Artwork Pipeline';

    protected string $widgetFormat = 'number';

    protected int $widgetPrecision = 0;

    protected bool $showDelta = false;

    protected function getData(): BreakdownWidgetData
    {
        return new BreakdownWidgetData([
            new BreakdownItemData('Waiting upload', (float) JobOrderTask::query()->whereNotIn('status', ['completed', 'cancelled'])->whereDoesntHave('artworks')->count(), color: 'warning'),
            new BreakdownItemData('Awaiting approval', (float) Artwork::query()->where('is_approved', false)->count(), color: 'primary'),
            new BreakdownItemData('Approved', (float) Artwork::query()->where('is_approved', true)->count(), color: 'success'),
        ]);
    }
}

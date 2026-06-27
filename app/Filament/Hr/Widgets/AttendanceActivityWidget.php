<?php

namespace App\Filament\Hr\Widgets;

use App\Filament\Widgets\Concerns\HasFilaWidgetMetrics;
use App\Models\AttendanceSegment;
use LaravelDaily\FilaWidgets\Data\HeatmapCalendarWidgetData;
use LaravelDaily\FilaWidgets\Widgets\HeatmapCalendarWidget;

class AttendanceActivityWidget extends HeatmapCalendarWidget
{
    use HasFilaWidgetMetrics;

    protected static ?int $sort = 3;

    protected ?string $widgetLabel = 'Attendance Activity';

    protected string $widgetFormat = 'number';

    protected int $widgetPrecision = 0;

    protected function getData(): HeatmapCalendarWidgetData
    {
        return new HeatmapCalendarWidgetData(
            entries: $this->heatmap(AttendanceSegment::query(), dateColumn: 'date', precision: 0),
            description: 'Daily attendance segments imported',
        );
    }
}

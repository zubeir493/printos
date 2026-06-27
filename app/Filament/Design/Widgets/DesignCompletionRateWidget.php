<?php

namespace App\Filament\Design\Widgets;

use App\Models\JobOrderTask;
use LaravelDaily\FilaWidgets\Data\CompletionRateWidgetData;
use LaravelDaily\FilaWidgets\Widgets\CompletionRateWidget;

class DesignCompletionRateWidget extends CompletionRateWidget
{
    protected static ?int $sort = 4;

    protected ?string $widgetLabel = 'Design Completion Rate';

    protected function getData(): CompletionRateWidgetData
    {
        $total = JobOrderTask::query()->where('status', '!=', 'cancelled')->count();
        $completed = JobOrderTask::query()->where('status', 'completed')->count();

        if ($total === 0) {
            return new CompletionRateWidgetData(0, isEmpty: true);
        }

        return new CompletionRateWidgetData(
            value: round(($completed / $total) * 100, 1),
            description: "{$completed} of {$total} design tasks completed",
        );
    }

    protected function getThresholds(): array
    {
        return [
            ['threshold' => 50, 'color' => 'danger', 'label' => 'Behind'],
            ['threshold' => 80, 'color' => 'warning', 'label' => 'Moving'],
            ['threshold' => 100, 'color' => 'success', 'label' => 'Healthy'],
        ];
    }
}

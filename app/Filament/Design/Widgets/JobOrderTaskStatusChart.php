<?php

namespace App\Filament\Design\Widgets;

use App\Models\JobOrderTask;
use Filament\Widgets\ChartWidget;

class JobOrderTaskStatusChart extends ChartWidget
{
    protected ?string $heading = 'Task Completion Rate';

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $distribution = JobOrderTask::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->orderBy('status')
            ->pluck('aggregate', 'status');

        return [
            'datasets' => [
                [
                    'label' => 'Tasks',
                    'data' => $distribution->values()->all(),
                    'backgroundColor' => ['#6366f1', '#0ea5e9', '#059669', '#e11d48', '#a78bfa'],
                ],
            ],
            'labels' => $distribution->keys()->map(fn ($status) => str($status)->headline()->toString())->all(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}

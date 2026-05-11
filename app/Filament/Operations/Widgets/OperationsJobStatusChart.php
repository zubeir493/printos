<?php

namespace App\Filament\Operations\Widgets;

use App\Models\JobOrder;
use Filament\Widgets\ChartWidget;

class OperationsJobStatusChart extends ChartWidget
{
    protected ?string $heading = 'Job Orders Pipeline';

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $statuses = ['draft', 'active', 'completed', 'cancelled'];
        $counts = [];

        foreach ($statuses as $status) {
            $counts[] = JobOrder::where('status', $status)->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Jobs',
                    'data' => $counts,
                    'backgroundColor' => ['#6366f1', '#0d9488', '#059669', '#e11d48'],
                ],
            ],
            'labels' => ['Draft', 'Active', 'Completed', 'Cancelled'],
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}

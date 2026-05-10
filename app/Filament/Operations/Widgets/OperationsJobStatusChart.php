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
        $statuses = ['draft', 'design', 'production', 'completed', 'cancelled'];
        $counts = [];
        
        foreach ($statuses as $status) {
            $counts[] = \App\Models\JobOrder::where('status', $status)->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Jobs',
                    'data' => $counts,
                    'backgroundColor' => ['#6366f1', '#0ea5e9', '#0d9488', '#059669', '#e11d48'],
                ],
            ],
            'labels' => ['Draft', 'Design', 'Production', 'Completed', 'Cancelled'],
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}

<?php

namespace App\Filament\Sales\Widgets;

use App\Models\SalesOrder;
use Filament\Widgets\ChartWidget;

class SalesPipelineChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Sales Pipeline by Stage';

    protected function getData(): array
    {
        $statuses = ['draft', 'pending', 'approved', 'completed', 'cancelled'];

        return [
            'datasets' => [
                [
                    'label' => 'Orders',
                    'data' => collect($statuses)
                        ->map(fn (string $status) => SalesOrder::query()->where('status', $status)->count())
                        ->all(),
                    'backgroundColor' => ['#6366f1', '#38bdf8', '#0d9488', '#059669', '#e11d48'],
                ],
            ],
            'labels' => ['Draft', 'Pending', 'Approved', 'Completed', 'Cancelled'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}

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
        $statuses = array_keys(SalesOrder::statusOptions());

        return [
            'datasets' => [
                [
                    'label' => 'Orders',
                    'data' => collect($statuses)
                        ->map(fn (string $status) => SalesOrder::query()->where('status', $status)->count())
                        ->all(),
                    'backgroundColor' => ['#6366f1', '#f59e0b', '#38bdf8', '#059669', '#e11d48'],
                ],
            ],
            'labels' => array_values(SalesOrder::statusOptions()),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}

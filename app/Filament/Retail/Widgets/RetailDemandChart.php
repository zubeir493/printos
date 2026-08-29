<?php

namespace App\Filament\Retail\Widgets;

use App\Models\SalesOrder;
use Filament\Widgets\ChartWidget;

class RetailDemandChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = '7-Day Counter Demand';

    protected function getData(): array
    {
        $days = collect(range(6, 0));

        return [
            'datasets' => [
                [
                    'label' => 'Paid-now sales value',
                    'data' => $days
                        ->map(fn (int $daysAgo) => (float) SalesOrder::query()
                            ->where('payment_mode', 'cash')
                            ->whereDate('order_date', today()->subDays($daysAgo))
                            ->sum('total'))
                        ->all(),
                    'borderColor' => '#6366f1',
                    'backgroundColor' => 'rgba(99, 102, 241, 0.15)',
                    'fill' => true,
                    'tension' => 0.35,
                ],
            ],
            'labels' => $days
                ->map(fn (int $daysAgo) => today()->subDays($daysAgo)->format('D'))
                ->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}

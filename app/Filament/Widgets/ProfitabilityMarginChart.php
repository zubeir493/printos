<?php

namespace App\Filament\Widgets;

use App\Models\JobOrder;
use App\Models\SalesOrder;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class ProfitabilityMarginChart extends ChartWidget
{
    protected ?string $heading = 'Revenue vs Expected (MTD)';

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $data = [];
        $potential = [];

        for ($i = 14; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $dateLabel = $date->format('M d');

            // Real Revenue - Completed Sales Orders
            $data[$dateLabel] = SalesOrder::whereDate('created_at', $date)
                ->where('status', 'completed')
                ->sum('total');

            // Potential Revenue - Job Orders In Pipeline
            $potential[$dateLabel] = JobOrder::whereDate('created_at', $date)
                ->whereIn('status', ['design', 'production'])
                ->sum('total');
        }

        return [
            'datasets' => [
                [
                    'label' => 'Realized Revenue (SO)',
                    'data' => array_values($data),
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.2)',
                    'fill' => 'start',
                    'tension' => 0.4,
                ],
                [
                    'label' => 'Pipeline Value (JO)',
                    'data' => array_values($potential),
                    'borderColor' => '#3b82f6',
                    'borderDash' => [5, 5],
                    'backgroundColor' => 'transparent',
                    'pointBackgroundColor' => '#3b82f6',
                    'pointBorderColor' => '#3b82f6',
                ],
            ],
            'labels' => array_keys($data),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}

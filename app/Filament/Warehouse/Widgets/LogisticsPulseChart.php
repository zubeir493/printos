<?php

namespace App\Filament\Warehouse\Widgets;

use App\Models\Dispatch;
use App\Models\GoodsReceipt;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class LogisticsPulseChart extends ChartWidget
{
    protected ?string $heading = 'Daily Logistics Pulse';

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $inbound = [];
        $outbound = [];
        $labels = [];

        for ($i = 13; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $labels[] = $date->format('M d');

            $inbound[] = GoodsReceipt::whereDate('created_at', $date)->count();
            $outbound[] = Dispatch::whereDate('created_at', $date)->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Inbound (Goods Receipts)',
                    'data' => $inbound,
                    'backgroundColor' => '#6366f1', // Indigo
                ],
                [
                    'label' => 'Outbound (Dispatches)',
                    'data' => $outbound,
                    'backgroundColor' => '#0d9488', // Teal
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}

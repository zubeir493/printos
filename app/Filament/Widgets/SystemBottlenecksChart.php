<?php

namespace App\Filament\Widgets;

use App\Models\Artwork;
use App\Models\JobOrder;
use App\Models\PurchaseOrder;
use Filament\Widgets\ChartWidget;

class SystemBottlenecksChart extends ChartWidget
{
    protected ?string $heading = 'System Bottlenecks';

    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $joPending = JobOrder::where('status', 'draft')->count();
        $artworkPending = Artwork::where('is_approved', false)->count();
        $poPending = PurchaseOrder::whereNotIn('status', ['received', 'cancelled'])->count();

        return [
            'datasets' => [
                [
                    'label' => 'Pending Items',
                    'data' => [$joPending, $artworkPending, $poPending],
                    'backgroundColor' => [
                        '#6366f1', // Indigo - Job Orders
                        '#a78bfa', // Violet - Artworks
                        '#0ea5e9', // Sky - Purchase Orders
                    ],
                    'borderColor' => [
                        '#4f46e5',
                        '#7c3aed',
                        '#0284c7',
                        '#3b82f6',
                    ],
                    'borderWidth' => 2,
                ],
            ],
            'labels' => ['Draft Job Orders', 'Pending Artworks', 'Awaiting Purchases'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}

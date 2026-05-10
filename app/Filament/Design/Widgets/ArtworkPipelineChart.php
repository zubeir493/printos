<?php

namespace App\Filament\Design\Widgets;

use App\Models\Artwork;
use Filament\Widgets\ChartWidget;

class ArtworkPipelineChart extends ChartWidget
{
    protected ?string $heading = 'Artwork Pipeline';

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $approved = Artwork::where('is_approved', true)->count();
        $pending = Artwork::where('is_approved', false)->count();

        return [
            'datasets' => [
                [
                    'label' => "Approved ({$approved})",
                    'data' => [$approved],
                    'backgroundColor' => '#059669',
                    'borderWidth' => 0,
                    'borderRadius' => ['topLeft' => 8, 'bottomLeft' => 8, 'topRight' => 0, 'bottomRight' => 0],
                    'borderSkipped' => false,
                ],
                [
                    'label' => "Awaiting ({$pending})",
                    'data' => [$pending],
                    'backgroundColor' => '#a78bfa',
                    'borderWidth' => 0,
                    'borderRadius' => ['topLeft' => 0, 'bottomLeft' => 0, 'topRight' => 8, 'bottomRight' => 8],
                    'borderSkipped' => false,
                ],
            ],
            'labels' => [''],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'responsive' => true,
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                    'labels' => [
                        'usePointStyle' => true,
                        'pointStyle' => 'circle',
                        'padding' => 20,
                    ],
                ],
                'tooltip' => [
                    'callbacks' => [],
                ],
            ],
            'scales' => [
                'x' => [
                    'stacked' => true,
                    'display' => false,
                ],
                'y' => [
                    'stacked' => true,
                    'display' => false,
                ],
            ],
        ];
    }
}

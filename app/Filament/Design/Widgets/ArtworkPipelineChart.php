<?php

namespace App\Filament\Design\Widgets;

use App\Models\Artwork;
use App\Models\JobOrder;
use Filament\Widgets\ChartWidget;

class ArtworkPipelineChart extends ChartWidget
{
    protected ?string $heading = 'Artwork Pipeline';

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $waitingUpload = JobOrder::query()
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereDoesntHave('artworks')
            ->count();

        $awaitingApproval = Artwork::where('is_approved', false)->count();
        $approved = Artwork::where('is_approved', true)->count();

        return [
            'datasets' => [
                [
                    'label' => 'Artwork jobs',
                    'data' => [$waitingUpload, $awaitingApproval, $approved],
                    'backgroundColor' => ['#f59e0b', '#6366f1', '#059669'],
                    'borderColor' => '#ffffff',
                    'borderWidth' => 2,
                ],
            ],
            'labels' => [
                "Waiting Upload ({$waitingUpload})",
                "Awaiting Approval ({$awaitingApproval})",
                "Approved ({$approved})",
            ],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}

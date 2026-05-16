<?php

namespace App\Filament\Design\Widgets;

use App\Models\Artwork;
use App\Models\JobOrderTask;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class DesignSLAStats extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $approvalTimeExpression = match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => 'AVG(TIMESTAMPDIFF(DAY, created_at, updated_at)) as avg_days',
            'pgsql' => 'AVG(EXTRACT(DAY FROM updated_at - created_at)) as avg_days',
            default => 'AVG(JULIANDAY(updated_at) - JULIANDAY(created_at)) as avg_days',
        };

        // 1. Avg Approval Time (days)
        $avgApprovalDays = Artwork::where('is_approved', true)
            ->selectRaw($approvalTimeExpression)
            ->value('avg_days') ?? 0;

        // 2. Count of active design tasks that still need design/artwork work.
        $designQueueCount = JobOrderTask::query()
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->where(function ($query) {
                $query
                    ->whereIn('status', ['pending', 'draft', 'design'])
                    ->orWhereDoesntHave('artworks');
            })
            ->count();

        // 3. Recently approved work, separate from the pipeline distribution chart.
        $approvedThisWeek = Artwork::where('is_approved', true)
            ->where('updated_at', '>=', now()->subDays(7)->startOfDay())
            ->count();

        return [
            Stat::make('Avg Approval Time', round($avgApprovalDays, 1).' Days')
                ->description('From upload to approval')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('Job Design Queue', $designQueueCount)
                ->description('Orders awaiting design work')
                ->descriptionIcon('heroicon-m-paint-brush')
                ->color($designQueueCount > 10 ? 'danger' : 'success'),

            Stat::make('Approved This Week', $approvedThisWeek)
                ->description('Artwork approvals in the last 7 days')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color($approvedThisWeek > 0 ? 'success' : 'gray'),
        ];
    }
}

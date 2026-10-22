<?php

namespace App\Filament\Widgets;

use App\Models\JobOrder;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class JobOrdersStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalJobs = JobOrder::count();
        $totalValue = JobOrder::sum('total');
        $activeJobs = JobOrder::inProgress()->count();
        $lateJobs = JobOrder::late()->count();
        $completedToday = JobOrder::where('status', 'completed')
            ->whereDate('completed_at', today())
            ->count();

        return [
            Stat::make('Active Jobs', $activeJobs)
                ->description('Open workflow')
                ->descriptionIcon('heroicon-m-cog')
                ->color($activeJobs > 20 ? 'warning' : 'primary'),
            Stat::make('Late Job Orders', $lateJobs)
                ->description('Submission date is before today')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($lateJobs > 0 ? 'danger' : 'success'),
            Stat::make('Completed Today', $completedToday)
                ->description('Finished orders')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
        ];
    }
}

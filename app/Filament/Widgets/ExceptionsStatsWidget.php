<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class ExceptionsStatsWidget extends BaseWidget
{
    protected static ?int $sort = 5;

    protected function getStats(): array
    {
        $totalExceptions = DB::table('filament_exceptions_table')->count();
        $todayExceptions = DB::table('filament_exceptions_table')
            ->whereDate('created_at', today())
            ->count();
        $weekExceptions = DB::table('filament_exceptions_table')
            ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->count();
        $criticalExceptions = DB::table('filament_exceptions_table')
            ->where('code', '500')
            ->count();

        return [
            Stat::make('Total Exceptions', $totalExceptions)
                ->description('All time')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color('warning'),
            Stat::make('Today', $todayExceptions)
                ->description('Exceptions today')
                ->descriptionIcon('heroicon-o-calendar')
                ->color($todayExceptions > 0 ? 'danger' : 'success'),
            Stat::make('This Week', $weekExceptions)
                ->description('Exceptions this week')
                ->descriptionIcon('heroicon-o-chart-bar')
                ->color('primary'),
            Stat::make('Critical (500)', $criticalExceptions)
                ->description('Server errors')
                ->descriptionIcon('heroicon-o-server')
                ->color($criticalExceptions > 0 ? 'danger' : 'success'),
        ];
    }
}

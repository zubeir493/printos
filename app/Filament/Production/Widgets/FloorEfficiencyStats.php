<?php

namespace App\Filament\Production\Widgets;

use App\Models\JobOrderTask;
use App\Models\ProductionPlanItem;
use App\Models\ProductionReportItem;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FloorEfficiencyStats extends BaseWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        // 1. Production Yield (Planned vs Actual) - Last 7 Days
        $plannedQty = ProductionPlanItem::whereHas('productionPlanMachine.productionPlan', function ($q) {
            $q->where('week_start', '>=', now()->subDays(7));
        })->sum('planned_quantity');

        $actualQty = ProductionReportItem::where('date', '>=', now()->subDays(7))
            ->sum('actual_quantity');

        $yieldPercentage = $plannedQty > 0 ? ($actualQty / $plannedQty) * 100 : 0;

        // 2. Active Machines (those mentioned in recent reports today)
        $activeMachines = ProductionReportItem::query()
            ->join('production_report_machines', 'production_report_items.production_report_machine_id', '=', 'production_report_machines.id')
            ->join('production_plan_machines', 'production_report_machines.production_plan_machine_id', '=', 'production_plan_machines.id')
            ->whereDate('production_report_items.date', now())
            ->distinct('production_plan_machines.machine_id')
            ->count();

        // 3. Job Tasks in Queue
        $pendingTasks = JobOrderTask::where('status', 'pending')->count();

        return [
            Stat::make('7D Production Yield', round($yieldPercentage, 1).'%')
                ->description('Planned vs Actual output')
                ->descriptionIcon($yieldPercentage > 90 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($yieldPercentage > 90 ? 'success' : 'warning'),

            Stat::make('Machines Active Today', $activeMachines)
                ->description('Machines with logs today')
                ->color('primary'),

            Stat::make('Tasks in Queue', $pendingTasks)
                ->description('Job tasks awaiting start')
                ->color('info'),
        ];
    }
}

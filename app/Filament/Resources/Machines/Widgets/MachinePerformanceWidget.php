<?php

namespace App\Filament\Resources\Machines\Widgets;

use App\Models\Machine;
use App\Models\ProductionPlan;
use App\Models\ProductionReport;
use Filament\Widgets\ChartWidget;

class MachinePerformanceWidget extends ChartWidget
{
    protected ?string $heading = 'Machine Weekly Performance';

    public ?Machine $record = null;

    protected function getData(): array
    {
        if (! $this->record) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        $machine = $this->record;
        $baseline = $machine->baseline_rounds_per_week ?? 0;

        // Get the last 8 weeks of data
        $weeks = [];
        $baselineData = [];
        $plannedData = [];
        $reportedData = [];

        for ($i = 7; $i >= 0; $i--) {
            $weekStart = now()->subWeeks($i)->startOfWeek();
            $weekEnd = now()->subWeeks($i)->endOfWeek();
            $weekLabel = $weekStart->format('M d').' - '.$weekEnd->format('M d');

            $weeks[] = $weekLabel;
            $baselineData[] = $baseline;

            // Get planned rounds from production plans for this week
            $plannedRounds = ProductionPlan::whereHas('machines', function ($query) use ($machine) {
                $query->where('machine_id', $machine->id);
            })
                ->whereBetween('week_start', [$weekStart, $weekEnd])
                ->with(['machines.items'])
                ->get()
                ->sum(function ($plan) {
                    return $plan->machines->sum(function ($planMachine) {
                        return $planMachine->items->sum('planned_rounds');
                    });
                });

            $plannedData[] = $plannedRounds;

            // Get reported rounds from production reports for this week
            $reportedRounds = ProductionReport::whereHas('machines', function ($query) use ($machine) {
                $query->whereHas('productionPlanMachine', function ($subQuery) use ($machine) {
                    $subQuery->where('machine_id', $machine->id);
                });
            })
                ->whereHas('productionPlan', function ($query) use ($weekStart, $weekEnd) {
                    $query->whereBetween('week_start', [$weekStart, $weekEnd]);
                })
                ->with(['machines.items'])
                ->get()
                ->sum(function ($report) {
                    return $report->machines->sum(function ($reportMachine) {
                        return $reportMachine->items->sum('rounds');
                    });
                });

            $reportedData[] = $reportedRounds;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Baseline',
                    'data' => $baselineData,
                    'borderColor' => 'rgb(99, 102, 241)',
                    'backgroundColor' => 'rgba(99, 102, 241, 0.1)',
                    'borderDash' => [5, 5],
                ],
                [
                    'label' => 'Planned',
                    'data' => $plannedData,
                    'borderColor' => 'rgb(13, 148, 136)',
                    'backgroundColor' => 'rgba(13, 148, 136, 0.1)',
                ],
                [
                    'label' => 'Reported',
                    'data' => $reportedData,
                    'borderColor' => 'rgb(14, 165, 233)',
                    'backgroundColor' => 'rgba(14, 165, 233, 0.1)',
                ],
            ],
            'labels' => $weeks,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Rounds',
                    ],
                ],
                'x' => [
                    'title' => [
                        'display' => true,
                        'text' => 'Week',
                    ],
                ],
            ],
        ];
    }
}

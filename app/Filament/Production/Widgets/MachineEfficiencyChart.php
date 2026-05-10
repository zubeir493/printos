<?php

namespace App\Filament\Production\Widgets;

use App\Models\ProductionPlanItem;
use App\Models\ProductionReportItem;
use Filament\Widgets\ChartWidget;

class MachineEfficiencyChart extends ChartWidget
{
    protected ?string $heading = 'Machine Efficiency';

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $start = now()->startOfWeek();
        $end = now()->endOfWeek();

        $planned = ProductionPlanItem::query()
            ->join('production_plan_machines', 'production_plan_items.production_plan_machine_id', '=', 'production_plan_machines.id')
            ->join('production_plans', 'production_plan_machines.production_plan_id', '=', 'production_plans.id')
            ->join('machines', 'production_plan_machines.machine_id', '=', 'machines.id')
            ->whereDate('production_plans.week_start', '<=', $end)
            ->whereDate('production_plans.week_end', '>=', $start)
            ->groupBy('machines.name')
            ->orderBy('machines.name')
            ->selectRaw('machines.name as machine_name, SUM(production_plan_items.planned_quantity) as total_planned')
            ->pluck('total_planned', 'machine_name');

        $actual = ProductionReportItem::query()
            ->join('production_report_machines', 'production_report_items.production_report_machine_id', '=', 'production_report_machines.id')
            ->join('production_plan_machines', 'production_report_machines.production_plan_machine_id', '=', 'production_plan_machines.id')
            ->join('machines', 'production_plan_machines.machine_id', '=', 'machines.id')
            ->whereBetween('production_report_items.date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('machines.name')
            ->orderBy('machines.name')
            ->selectRaw('machines.name as machine_name, SUM(production_report_items.actual_quantity) as total_actual')
            ->pluck('total_actual', 'machine_name');

        $labels = $planned->keys()->merge($actual->keys())->unique()->values();

        if ($labels->isEmpty()) {
            $labels = collect(['No data']);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Planned Quantity',
                    'data' => $labels->map(fn ($label) => (float) ($planned[$label] ?? 0))->all(),
                    'backgroundColor' => '#6366f1',
                ],
                [
                    'label' => 'Actual Quantity',
                    'data' => $labels->map(fn ($label) => (float) ($actual[$label] ?? 0))->all(),
                    'backgroundColor' => '#0d9488',
                ],
            ],
            'labels' => $labels->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}

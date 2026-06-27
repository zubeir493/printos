<?php

namespace App\Filament\Production\Widgets;

use App\Filament\Widgets\Concerns\HasFilaWidgetMetrics;
use App\Models\ProductionPlanItem;
use LaravelDaily\FilaWidgets\Data\BreakdownWidgetData;
use LaravelDaily\FilaWidgets\Widgets\BreakdownWidget;

class MachineLoadMixWidget extends BreakdownWidget
{
    use HasFilaWidgetMetrics;

    protected static ?int $sort = 3;

    protected ?string $widgetLabel = 'Machine Load Mix';

    protected string $widgetFormat = 'number';

    protected int $widgetPrecision = 0;

    protected bool $showDelta = false;

    protected ?int $itemLimit = 6;

    protected bool $groupOther = true;

    protected function getData(): BreakdownWidgetData
    {
        [$start, $end] = $this->currentPeriod();

        return BreakdownWidgetData::fromCollection(
            ProductionPlanItem::query()
                ->join('production_plan_machines', 'production_plan_items.production_plan_machine_id', '=', 'production_plan_machines.id')
                ->join('production_plans', 'production_plan_machines.production_plan_id', '=', 'production_plans.id')
                ->join('machines', 'production_plan_machines.machine_id', '=', 'machines.id')
                ->whereDate('production_plans.week_start', '<=', $end)
                ->whereDate('production_plans.week_end', '>=', $start)
                ->groupBy('machines.name')
                ->orderByDesc('planned_quantity_sum')
                ->selectRaw('machines.name as machine_name, SUM(production_plan_items.planned_quantity) as planned_quantity_sum')
                ->get(),
            labelKey: 'machine_name',
            valueKey: 'planned_quantity_sum',
        );
    }
}

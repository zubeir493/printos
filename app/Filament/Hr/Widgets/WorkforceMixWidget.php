<?php

namespace App\Filament\Hr\Widgets;

use App\Models\Employee;
use LaravelDaily\FilaWidgets\Data\BreakdownWidgetData;
use LaravelDaily\FilaWidgets\Widgets\BreakdownWidget;

class WorkforceMixWidget extends BreakdownWidget
{
    protected static ?int $sort = 2;

    protected ?string $widgetLabel = 'Workforce Mix';

    protected string $widgetFormat = 'number';

    protected int $widgetPrecision = 0;

    protected bool $showDelta = false;

    protected ?int $itemLimit = 6;

    protected bool $groupOther = true;

    protected function getData(): BreakdownWidgetData
    {
        return BreakdownWidgetData::fromCollection(
            Employee::query()
                ->selectRaw('COALESCE(NULLIF(department, ""), "Unassigned") as department_name, COUNT(*) as employees_count')
                ->where('status', 'Active')
                ->groupBy('department_name')
                ->orderByDesc('employees_count')
                ->get(),
            labelKey: 'department_name',
            valueKey: 'employees_count',
        );
    }
}

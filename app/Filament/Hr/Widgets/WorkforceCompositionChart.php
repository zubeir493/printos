<?php

namespace App\Filament\Hr\Widgets;

use App\Models\Employee;
use Filament\Widgets\ChartWidget;

class WorkforceCompositionChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Workforce by Department';

    protected function getData(): array
    {
        $departments = Employee::query()
            ->select('department')
            ->selectRaw('COUNT(*) as employees_count')
            ->where('status', 'Active')
            ->groupBy('department')
            ->orderByDesc('employees_count')
            ->limit(6)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Employees',
                    'data' => $departments->pluck('employees_count')->all(),
                    'backgroundColor' => ['#ec4899', '#f97316', '#14b8a6', '#6366f1', '#84cc16', '#64748b'],
                ],
            ],
            'labels' => $departments
                ->pluck('department')
                ->map(fn ($department) => $department ?: 'Unassigned')
                ->all(),
        ];
    }

    protected function getType(): string
    {
        return 'polarArea';
    }
}

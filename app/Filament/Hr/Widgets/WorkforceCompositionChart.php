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
                    'backgroundColor' => ['#6366f1', '#0ea5e9', '#0d9488', '#059669', '#a78bfa', '#475569'],
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

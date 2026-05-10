<?php

namespace App\Filament\Hr\Widgets;

use App\Models\Employee;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class EmployeeSalaryTrendChart extends ChartWidget
{
    public Employee $record;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Salary Trend';

    protected ?string $description = 'Monthly salary changes recorded for this employee.';

    protected ?string $maxHeight = '300px';

    protected static ?int $sort = 1;

    protected function getData(): array
    {
        $histories = $this->record->salaryHistories()
            ->orderBy('effective_date')
            ->get();

        $labels = [];
        $basicSalary = [];
        $hourlyOvertimeRate = [];
        $holidayOvertimeRate = [];

        if ($histories->isEmpty()) {
            $labels[] = 'Current';
            $basicSalary[] = (float) $this->record->basic_salary;
            $hourlyOvertimeRate[] = (float) $this->record->hourly_overtime_rate;
            $holidayOvertimeRate[] = (float) $this->record->holiday_overtime_rate;
        } else {
            foreach ($histories as $history) {
                $labels[] = Carbon::parse($history->effective_date)->format('M d, Y');
                $basicSalary[] = (float) $history->basic_salary;
                $hourlyOvertimeRate[] = (float) $history->hourly_overtime_rate;
                $holidayOvertimeRate[] = (float) $history->holiday_overtime_rate;
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Basic Salary',
                    'data' => $basicSalary,
                    'borderColor' => '#6366f1',
                    'backgroundColor' => 'rgba(99, 102, 241, 0.15)',
                    'fill' => 'start',
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Hourly Overtime Rate',
                    'data' => $hourlyOvertimeRate,
                    'borderColor' => '#0ea5e9',
                    'backgroundColor' => 'rgba(14, 165, 233, 0.15)',
                    'fill' => false,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Holiday Overtime Rate',
                    'data' => $holidayOvertimeRate,
                    'borderColor' => '#0d9488',
                    'backgroundColor' => 'rgba(13, 148, 136, 0.15)',
                    'fill' => false,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
        ];
    }
}

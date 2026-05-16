<?php

namespace App\Filament\Hr\Widgets;

use App\Models\Employee;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class HrPanelStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $activeEmployees = Employee::query()->where('status', 'Active');

        $activeHeadcount = $activeEmployees->count();
        $monthlyPayrollLiability = (float) Employee::query()
            ->where('status', 'Active')
            ->sum('basic_salary');
        $recentHires = Employee::query()
            ->whereDate('hire_date', '>=', now()->subDays(30)->startOfDay())
            ->count();

        return [
            Stat::make('Active Headcount', $activeHeadcount)
                ->description('Employees with active status')
                ->color('success'),
            Stat::make('Monthly Payroll Liability', Money::abbreviate($monthlyPayrollLiability, precision: 2))
                ->description('Active employees basic salary total')
                ->color('warning'),
            Stat::make('Recent Hires', $recentHires)
                ->description('Joined in the last 30 days')
                ->color('primary'),
        ];
    }
}

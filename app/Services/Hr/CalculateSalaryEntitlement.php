<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\PayrollRun;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class CalculateSalaryEntitlement
{
    public function __construct(private ResolveEmployeeWork $work) {}

    /** @return array{basic: float, allowance: float, pension_base: float, portions: list<array<string, mixed>>, unpaid_reduction: float, unpaid_minutes: int, hourly_rate: float, issues: list<string>} */
    public function handle(Employee $employee, PayrollRun $run, Collection $summaries): array
    {
        $start = CarbonImmutable::parse($run->period_start)->startOfDay();
        $end = CarbonImmutable::parse($run->period_end)->startOfDay();
        $days = (int) $start->diffInDays($end) + 1;
        $histories = $employee->salaryHistories()->whereDate('effective_date', '<=', $end)->orderByDesc('effective_date')->orderByDesc('id')->get();
        $result = ['basic' => 0.0, 'allowance' => 0.0, 'pension_base' => 0.0, 'portions' => [], 'unpaid_reduction' => 0.0, 'unpaid_minutes' => 0, 'hourly_rate' => 0.0, 'issues' => []];
        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            if ($date->lt($employee->hire_date) || ($employee->termination_date && $date->gt($employee->termination_date))) {
                continue;
            }
            $work = $this->work->forDate($employee, $date->toDateString());
            $policy = $work['policy'];
            $terms = $histories->first(fn ($history): bool => $history->effective_date->toDateString() <= $date->toDateString());
            $salary = (float) ($terms?->basic_salary ?? $employee->basic_salary);
            $allowance = (float) ($terms?->transport_allowance ?? $employee->transport_allowance);
            $pension = (bool) ($terms?->pension_enabled ?? $employee->pension_enabled);
            $divisor = $run->period_type === 'monthly' ? $days : (int) $policy->monthly_day_divisor;
            $summary = $summaries->first(fn ($summary): bool => $summary->date->toDateString() === $date->toDateString());
            $minutes = (int) ($summary?->absence_minutes ?? 0);
            if ($policy->deduct_lateness) {
                $minutes += max(0, (int) ($summary?->late_minutes ?? 0) - (int) ($summary?->paid_leave_minutes ?? 0) - (int) ($summary?->unpaid_leave_minutes ?? 0));
            }
            $minutes = min($minutes, (int) ($summary?->expected_minutes ?? 0));
            $hourly = $policy->hourlyRate($salary);
            $reduction = min($salary / $divisor, $minutes / 60 * $hourly);
            $dailyBasic = max(0, $salary / $divisor - $reduction);
            $dailyAllowance = $allowance / $divisor;
            if ($policy->prorate_allowance && $summary?->expected_minutes > 0) {
                $dailyAllowance *= max(0, 1 - $minutes / $summary->expected_minutes);
            }
            $result['basic'] += $dailyBasic;
            $result['allowance'] += $dailyAllowance;
            $result['pension_base'] += $pension ? $dailyBasic : 0;
            $result['unpaid_reduction'] += $reduction;
            $result['unpaid_minutes'] += $minutes;
            $result['hourly_rate'] = $hourly;
            $result['issues'] = [...$result['issues'], ...($summary?->calculation_snapshot['issues'] ?? [])];
            $result['portions'][] = ['date' => $date->toDateString(), 'salary_history_id' => $terms?->id, 'monthly_basic' => $salary, 'monthly_allowance' => $allowance, 'pension_enabled' => $pension, 'divisor' => $divisor, 'policy' => $policy->attributesToArray(), 'assignment_id' => $work['assignment']->id, 'earned_basic' => $dailyBasic, 'unpaid_minutes' => $minutes, 'unpaid_reduction' => $reduction];
        }
        foreach (['basic', 'allowance', 'pension_base', 'unpaid_reduction'] as $field) {
            $result[$field] = round($result[$field], 2);
        }
        $result['issues'] = array_values(array_unique($result['issues']));

        return $result;
    }

    public function salaryOn(Employee $employee, string $date): float
    {
        return (float) ($employee->salaryHistories()->whereDate('effective_date', '<=', $date)->orderByDesc('effective_date')->orderByDesc('id')->value('basic_salary') ?? $employee->basic_salary);
    }
}

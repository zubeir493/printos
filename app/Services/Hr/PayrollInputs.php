<?php

namespace App\Services\Hr;

use App\Models\Employee;

class PayrollInputs
{
    /** @return array<string, \Closure> */
    public static function relations(string $start, string $end): array
    {
        return [
            'salaryHistories' => fn ($query) => $query->whereDate('effective_date', '<=', $end)->orderByDesc('effective_date')->orderByDesc('id'),
            'attendanceDailySummaries' => fn ($query) => $query->whereDate('date', '>=', $start)->whereDate('date', '<=', $end)->orderBy('date'),
            'attendancePeriodSummaries' => fn ($query) => $query->whereDate('period_start', $start)->whereDate('period_end', $end)->orderBy('id'),
            'leaveRequests' => fn ($query) => $query->with('leaveType')->where('status', 'approved')->whereDate('start_date', '<=', $end)->whereDate('end_date', '>=', $start)->orderBy('id'),
        ];
    }

    public static function signature(Employee $employee, string $start, string $end): string
    {
        $employee->loadMissing(self::relations($start, $end));

        return hash('sha256', json_encode([
            $employee->only(['hire_date', 'termination_date', 'basic_salary', 'transport_allowance', 'pension_enabled']),
            $employee->salaryHistories->map->only(['basic_salary', 'effective_date'])->all(),
            $employee->attendanceDailySummaries->map->only(['date', 'expected_minutes', 'worked_minutes', 'absence_minutes', 'late_minutes', 'overtime_minutes', 'holiday_minutes', 'status'])->all(),
            $employee->attendancePeriodSummaries->map->only(['shift_type', 'work_days', 'actual_days', 'absent_days', 'late_minutes', 'early_minutes', 'overtime_minutes', 'holiday_days', 'dayoff_days', 'work_time_hours'])->all(),
            $employee->leaveRequests->map(fn ($request): array => [...$request->only(['start_date', 'end_date', 'minutes']), 'is_paid' => $request->leaveType->is_paid])->all(),
        ], JSON_THROW_ON_ERROR));
    }
}

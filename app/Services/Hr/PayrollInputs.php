<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\PayrollOvertimeEntry;
use App\Models\PayrollTaxRule;
use App\Models\Setting;

class PayrollInputs
{
    /** @return array<string, \Closure> */
    public static function relations(string $start, string $end): array
    {
        return [
            'attendanceSegments' => fn ($query) => $query->whereDate('date', '>=', $start)->whereDate('date', '<=', $end)->orderBy('id'),
            'salaryHistories' => fn ($query) => $query->whereDate('effective_date', '<=', $end)->orderByDesc('effective_date')->orderByDesc('id'),
            'attendanceDailySummaries' => fn ($query) => $query->whereDate('date', '>=', $start)->whereDate('date', '<=', $end)->orderBy('date'),
            'attendancePeriodSummaries' => fn ($query) => $query->whereDate('period_start', $start)->whereDate('period_end', $end)->orderBy('id'),
            'leaveRequests' => fn ($query) => $query->with('leaveType')->where('status', 'approved')->whereDate('start_date', '<=', $end)->whereDate('end_date', '>=', $start)->orderBy('id'),
            'workAssignments' => fn ($query) => $query->with(['payPolicy', 'workSchedule.days.shift', 'workSchedule.days.additionalShifts'])->whereDate('effective_from', '<=', $end)->where(fn ($dates) => $dates->whereNull('effective_to')->orWhereDate('effective_to', '>=', $start))->orderBy('effective_from'),
            'shiftOverrides' => fn ($query) => $query->with('shift', 'additionalShifts')->whereDate('date', '>=', $start)->whereDate('date', '<=', $end)->orderBy('date'),
        ];
    }

    public static function signature(Employee $employee, string $start, string $end): string
    {
        $employee->load(self::relations($start, $end));

        return hash('sha256', json_encode([
            $employee->only(['hire_date', 'termination_date', 'basic_salary', 'transport_allowance', 'pension_enabled']),
            $employee->salaryHistories->map->only(['basic_salary', 'transport_allowance', 'pension_enabled', 'effective_date'])->all(),
            $employee->attendanceSegments->map->only(['date', 'clock_in', 'clock_out', 'status', 'raw_data', 'correction_reason'])->all(),
            $employee->attendanceDailySummaries->map->only(['date', 'expected_minutes', 'worked_minutes', 'absence_minutes', 'late_minutes', 'overtime_minutes', 'paid_leave_minutes', 'unpaid_leave_minutes', 'status', 'calculation_snapshot'])->all(),
            $employee->leaveRequests->map(fn ($request): array => [...$request->only(['start_date', 'end_date', 'minutes']), 'is_paid' => $request->leaveType->is_paid])->all(),
            $employee->workAssignments->map(fn ($assignment): array => [
                ...$assignment->only(['effective_from', 'effective_to', 'work_schedule_id', 'pay_policy_id']),
                'policy' => $assignment->payPolicy->attributesToArray(),
                'days' => $assignment->workSchedule->days->sortBy('day_of_week')->map(fn ($day): array => ['weekday' => $day->day_of_week, 'shift' => $day->shift?->attributesToArray(), 'shifts' => $day->resolvedShifts()->map->attributesToArray()->all()])->values()->all(),
            ])->all(),
            $employee->shiftOverrides->map(fn ($override): array => ['date' => $override->date->toDateString(), 'shift' => $override->shift?->attributesToArray(), 'shifts' => $override->resolvedShifts()->map->attributesToArray()->all(), 'reason' => $override->reason])->all(),
            PayrollOvertimeEntry::where('employee_id', $employee->id)->whereNotNull('pay_policy_id')->whereDate('date', '>=', $start)->whereDate('date', '<=', $end)->orderBy('id')->get()->map->only(['id', 'status', 'minutes', 'hourly_rate', 'multiplier', 'amount', 'pay_policy_id', 'category', 'reviewed_by', 'reviewed_at'])->all(),
            Setting::getSettings()->only(['employee_pension_rate', 'employer_pension_rate', 'workers_union_enabled', 'workers_union_rate', 'currency_code']),
            PayrollTaxRule::with('brackets')->whereDate('effective_from', '<=', $end)->orderBy('id')->get()->toArray(),
        ], JSON_THROW_ON_ERROR));
    }
}

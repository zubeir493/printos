<?php

namespace App\Services\Hr;

use App\Models\AttendanceDailySummary;
use App\Models\AttendancePeriodSummary;
use App\Models\AttendanceSegment;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\OvertimeRule;
use App\Models\PayrollOvertimeEntry;
use App\Models\PayrollRun;
use App\Models\PayrollRunEmployee;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class GeneratePayrollOvertimeEntries
{
    /**
     * @param  EloquentCollection<int, AttendanceDailySummary>  $dailySummaries
     * @param  EloquentCollection<int, AttendancePeriodSummary>  $periodSummaries
     */
    public function syncForEmployee(
        PayrollRun $payrollRun,
        Employee $employee,
        EloquentCollection $dailySummaries,
        EloquentCollection $periodSummaries,
        float $fullBasicSalary,
        float $baseDays,
    ): void {
        $rules = $this->activeRules();

        if ($rules->isEmpty()) {
            return;
        }

        $regularHourlyRate = $fullBasicSalary / max(1, $baseDays) / 8;
        $periodStart = CarbonImmutable::parse($payrollRun->getRawOriginal('period_start'))->toDateString();
        $periodEnd = CarbonImmutable::parse($payrollRun->getRawOriginal('period_end'))->toDateString();
        $generatedKeys = collect();
        $segments = AttendanceSegment::query()
            ->with('shift')
            ->where('employee_id', $employee->id)
            ->whereDate('date', '>=', $periodStart)
            ->whereDate('date', '<=', $periodEnd)
            ->orderBy('date')
            ->get();

        if ($segments->isNotEmpty()) {
            $segments->each(function (AttendanceSegment $segment) use ($payrollRun, $employee, $rules, $regularHourlyRate, $generatedKeys): void {
                $this->syncSegmentCandidates($payrollRun, $employee, $segment, $rules, $regularHourlyRate, $generatedKeys);
            });
        } elseif ($dailySummaries->isNotEmpty()) {
            $dailySummaries->each(function (AttendanceDailySummary $summary) use ($payrollRun, $employee, $rules, $regularHourlyRate, $generatedKeys): void {
                $this->syncDailySummaryCandidates($payrollRun, $employee, $summary, $rules, $regularHourlyRate, $generatedKeys);
            });
        } else {
            $periodSummaries->each(function (AttendancePeriodSummary $summary) use ($payrollRun, $employee, $rules, $regularHourlyRate, $generatedKeys): void {
                $this->syncPeriodSummaryCandidates($payrollRun, $employee, $summary, $rules, $regularHourlyRate, $generatedKeys);
            });
        }

        $payrollRun->overtimeEntries()
            ->where('employee_id', $employee->id)
            ->where('status', PayrollOvertimeEntry::STATUS_PENDING)
            ->whereNotIn('source_key', $generatedKeys->all())
            ->delete();
    }

    /**
     * @return array{minutes: int, hours: float, amount: float, ids: array<int>}
     */
    public function approvedTotals(PayrollRun $payrollRun, Employee|PayrollRunEmployee $employee): array
    {
        $employeeId = $employee instanceof PayrollRunEmployee ? (int) $employee->employee_id : (int) $employee->id;
        $entries = $payrollRun->overtimeEntries()
            ->where('employee_id', $employeeId)
            ->where('status', PayrollOvertimeEntry::STATUS_APPROVED)
            ->get();

        return [
            'minutes' => (int) $entries->sum('minutes'),
            'hours' => round((float) $entries->sum('minutes') / 60, 2),
            'amount' => round((float) $entries->sum('amount'), 2),
            'ids' => $entries->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
        ];
    }

    public function linkEntriesToPayrollRow(PayrollRunEmployee $row): void
    {
        PayrollOvertimeEntry::query()
            ->where('payroll_run_id', $row->payroll_run_id)
            ->where('employee_id', $row->employee_id)
            ->update(['payroll_run_employee_id' => $row->id]);
    }

    /**
     * @return Collection<int, OvertimeRule>
     */
    private function activeRules(): Collection
    {
        return OvertimeRule::query()
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  Collection<int, OvertimeRule>  $rules
     * @param  Collection<int, string>  $generatedKeys
     */
    private function syncSegmentCandidates(
        PayrollRun $payrollRun,
        Employee $employee,
        AttendanceSegment $segment,
        Collection $rules,
        float $regularHourlyRate,
        Collection $generatedKeys,
    ): void {
        if ($segment->shift && ! $segment->shift->overtime_eligible) {
            return;
        }

        $dayType = $this->segmentDayType($segment);

        $rules->each(function (OvertimeRule $rule) use ($payrollRun, $employee, $segment, $dayType, $regularHourlyRate, $generatedKeys): void {
            if (! $rule->appliesToDay($dayType)) {
                return;
            }

            $minutes = $this->segmentMinutesForRule($segment, $rule);

            if ($minutes <= 0) {
                return;
            }

            $this->syncCandidate($payrollRun, $employee, $rule, [
                'date' => $this->segmentDate($segment),
                'source_type' => AttendanceSegment::class,
                'source_id' => $segment->id,
                'source_key' => 'attendance_segment:'.$segment->id.':rule:'.$rule->id,
                'minutes' => $minutes,
            ], $regularHourlyRate, $generatedKeys);
        });
    }

    /**
     * @param  Collection<int, OvertimeRule>  $rules
     * @param  Collection<int, string>  $generatedKeys
     */
    private function syncDailySummaryCandidates(
        PayrollRun $payrollRun,
        Employee $employee,
        AttendanceDailySummary $summary,
        Collection $rules,
        float $regularHourlyRate,
        Collection $generatedKeys,
    ): void {
        $date = CarbonImmutable::parse($summary->getRawOriginal('date'))->toDateString();
        $dayType = $this->dailySummaryDayType($summary);

        $rules->each(function (OvertimeRule $rule) use ($payrollRun, $employee, $summary, $date, $dayType, $regularHourlyRate, $generatedKeys): void {
            if (! $rule->appliesToDay($dayType)) {
                return;
            }

            $minutes = $this->dailySummaryMinutesForRule($summary, $rule);

            if ($minutes <= 0) {
                return;
            }

            $this->syncCandidate($payrollRun, $employee, $rule, [
                'date' => $date,
                'source_type' => AttendanceDailySummary::class,
                'source_id' => $summary->id,
                'source_key' => 'attendance_daily_summary:'.$summary->id.':rule:'.$rule->id,
                'minutes' => $minutes,
            ], $regularHourlyRate, $generatedKeys);
        });
    }

    /**
     * @param  Collection<int, OvertimeRule>  $rules
     * @param  Collection<int, string>  $generatedKeys
     */
    private function syncPeriodSummaryCandidates(
        PayrollRun $payrollRun,
        Employee $employee,
        AttendancePeriodSummary $summary,
        Collection $rules,
        float $regularHourlyRate,
        Collection $generatedKeys,
    ): void {
        $rules->each(function (OvertimeRule $rule) use ($payrollRun, $employee, $summary, $regularHourlyRate, $generatedKeys): void {
            foreach ($this->periodSummaryCandidatesForRule($summary, $rule) as $candidate) {
                $this->syncCandidate($payrollRun, $employee, $rule, $candidate, $regularHourlyRate, $generatedKeys);
            }
        });
    }

    /**
     * @param  array{date: string|null, source_type: class-string|string, source_id: int|null, source_key: string, minutes: int}  $candidate
     * @param  Collection<int, string>  $generatedKeys
     */
    private function syncCandidate(
        PayrollRun $payrollRun,
        Employee $employee,
        OvertimeRule $rule,
        array $candidate,
        float $regularHourlyRate,
        Collection $generatedKeys,
    ): void {
        $minutes = $this->roundedMinutes((int) $candidate['minutes'], $rule);

        if ($minutes <= 0) {
            return;
        }

        $hourlyRate = (float) ($rule->hourly_rate ?? $regularHourlyRate);
        $multiplier = (float) $rule->multiplier;
        $hours = round($minutes / 60, 2);
        $amount = round($hours * $hourlyRate * $multiplier, 2);
        $generatedKeys->push($candidate['source_key']);
        $entry = PayrollOvertimeEntry::query()->firstOrNew([
            'payroll_run_id' => $payrollRun->id,
            'employee_id' => $employee->id,
            'overtime_rule_id' => $rule->id,
            'source_key' => $candidate['source_key'],
        ]);

        $entry->fill([
            'date' => $candidate['date'],
            'source_type' => $candidate['source_type'],
            'source_id' => $candidate['source_id'],
            'status' => $entry->exists ? $entry->status : PayrollOvertimeEntry::STATUS_PENDING,
            'minutes' => $minutes,
            'hours' => $hours,
            'hourly_rate' => $hourlyRate,
            'multiplier' => $multiplier,
            'amount' => $entry->manual_amount !== null ? (float) $entry->manual_amount : $amount,
        ])->save();
    }

    private function roundedMinutes(int $minutes, OvertimeRule $rule): int
    {
        if ($minutes < (int) $rule->minimum_minutes) {
            return 0;
        }

        $increment = max(1, (int) $rule->rounding_increment_minutes);

        return (int) (floor($minutes / $increment) * $increment);
    }

    private function segmentMinutesForRule(AttendanceSegment $segment, OvertimeRule $rule): int
    {
        return match ($rule->minutes_basis) {
            OvertimeRule::BASIS_ATTENDANCE_OVERTIME => (int) $segment->overtime_minutes,
            OvertimeRule::BASIS_BEFORE_SHIFT => $this->beforeShiftMinutes($segment),
            OvertimeRule::BASIS_AFTER_SHIFT => $this->afterShiftMinutes($segment),
            OvertimeRule::BASIS_AFTER_CLOCK_TIME => $this->afterClockTimeMinutes($segment, (string) $rule->window_start_time),
            OvertimeRule::BASIS_BEFORE_CLOCK_TIME => $this->beforeClockTimeMinutes($segment, (string) $rule->window_end_time),
            OvertimeRule::BASIS_TIME_WINDOW => $this->clockWindowMinutes($segment, (string) $rule->window_start_time, (string) $rule->window_end_time),
            OvertimeRule::BASIS_WORKED_DAY => max(0, (int) $segment->worked_minutes + (int) $segment->overtime_minutes),
            default => 0,
        };
    }

    private function dailySummaryMinutesForRule(AttendanceDailySummary $summary, OvertimeRule $rule): int
    {
        return match ($rule->minutes_basis) {
            OvertimeRule::BASIS_ATTENDANCE_OVERTIME => (int) $summary->overtime_minutes,
            OvertimeRule::BASIS_TIME_WINDOW => (int) $summary->night_minutes,
            OvertimeRule::BASIS_WORKED_DAY => max((int) $summary->holiday_minutes, (int) $summary->worked_minutes + (int) $summary->overtime_minutes),
            default => 0,
        };
    }

    /**
     * @return array<int, array{date: string|null, source_type: class-string|string, source_id: int|null, source_key: string, minutes: int}>
     */
    private function periodSummaryCandidatesForRule(AttendancePeriodSummary $summary, OvertimeRule $rule): array
    {
        $candidates = [];

        if ($rule->appliesToDay(OvertimeRule::DAY_HOLIDAY) && (float) $summary->holiday_days > 0 && $rule->minutes_basis === OvertimeRule::BASIS_WORKED_DAY) {
            $candidates[] = [
                'date' => null,
                'source_type' => AttendancePeriodSummary::class,
                'source_id' => $summary->id,
                'source_key' => 'attendance_period_summary:'.$summary->id.':rule:'.$rule->id.':holiday',
                'minutes' => (int) round((float) $summary->holiday_days * 480),
            ];
        }

        if ($rule->appliesToDay(OvertimeRule::DAY_WEEKEND_DAYOFF) && (float) $summary->dayoff_days > 0 && $rule->minutes_basis === OvertimeRule::BASIS_WORKED_DAY) {
            $candidates[] = [
                'date' => null,
                'source_type' => AttendancePeriodSummary::class,
                'source_id' => $summary->id,
                'source_key' => 'attendance_period_summary:'.$summary->id.':rule:'.$rule->id.':dayoff',
                'minutes' => (int) round((float) $summary->dayoff_days * 480),
            ];
        }

        if ($rule->appliesToDay(OvertimeRule::DAY_REGULAR) && (int) $summary->overtime_minutes > 0) {
            $minutes = match ($rule->minutes_basis) {
                OvertimeRule::BASIS_ATTENDANCE_OVERTIME => (int) $summary->overtime_minutes,
                OvertimeRule::BASIS_TIME_WINDOW => $summary->shift_type === 'night' ? (int) $summary->overtime_minutes : 0,
                default => 0,
            };

            if ($minutes > 0) {
                $candidates[] = [
                    'date' => null,
                    'source_type' => AttendancePeriodSummary::class,
                    'source_id' => $summary->id,
                    'source_key' => 'attendance_period_summary:'.$summary->id.':rule:'.$rule->id.':regular',
                    'minutes' => $minutes,
                ];
            }
        }

        return $candidates;
    }

    private function segmentDayType(AttendanceSegment $segment): string
    {
        if ($this->isHoliday($segment)) {
            return OvertimeRule::DAY_HOLIDAY;
        }

        if ($this->isWeekendOrDayoff($segment)) {
            return OvertimeRule::DAY_WEEKEND_DAYOFF;
        }

        return OvertimeRule::DAY_REGULAR;
    }

    private function dailySummaryDayType(AttendanceDailySummary $summary): string
    {
        if ($summary->status === 'holiday') {
            return OvertimeRule::DAY_HOLIDAY;
        }

        if ($summary->status === 'dayoff' || CarbonImmutable::parse($summary->getRawOriginal('date'))->isSunday()) {
            return OvertimeRule::DAY_WEEKEND_DAYOFF;
        }

        return OvertimeRule::DAY_REGULAR;
    }

    private function isHoliday(AttendanceSegment $segment): bool
    {
        if ($this->isStatus($segment, 'Holiday')) {
            return true;
        }

        return Holiday::query()
            ->whereDate('date', $this->segmentDate($segment))
            ->where('counts_as_holiday_overtime', true)
            ->exists();
    }

    private function isWeekendOrDayoff(AttendanceSegment $segment): bool
    {
        return $this->isStatus($segment, 'Dayoff') || CarbonImmutable::parse($this->segmentDate($segment))->isSunday();
    }

    private function isStatus(AttendanceSegment $segment, string $status): bool
    {
        return strcasecmp((string) $segment->status, $status) === 0
            || strcasecmp((string) $segment->exception, $status) === 0;
    }

    private function beforeShiftMinutes(AttendanceSegment $segment): int
    {
        $interval = $this->segmentInterval($segment);
        $schedule = $this->scheduledInterval($segment);

        if (! $interval || ! $schedule) {
            return 0;
        }

        [$clockIn] = $interval;
        [$scheduledStart] = $schedule;

        return $clockIn->lt($scheduledStart) ? (int) $clockIn->diffInMinutes($scheduledStart) : 0;
    }

    private function afterShiftMinutes(AttendanceSegment $segment): int
    {
        $interval = $this->segmentInterval($segment);
        $schedule = $this->scheduledInterval($segment);

        if (! $interval || ! $schedule) {
            return 0;
        }

        [, $clockOut] = $interval;
        [, $scheduledEnd] = $schedule;

        return $clockOut->gt($scheduledEnd) ? (int) $scheduledEnd->diffInMinutes($clockOut) : 0;
    }

    private function afterClockTimeMinutes(AttendanceSegment $segment, string $time): int
    {
        if ($time === '') {
            return 0;
        }

        $interval = $this->segmentInterval($segment);

        if (! $interval) {
            return 0;
        }

        [$start, $end] = $interval;
        $threshold = $this->dateTimeFor($this->segmentDate($segment), $time);

        if ($threshold->lt($start)) {
            $threshold = $threshold->addDay();
        }

        $overlapStart = $start->gt($threshold) ? $start : $threshold;

        return $overlapStart->lt($end) ? (int) $overlapStart->diffInMinutes($end) : 0;
    }

    private function beforeClockTimeMinutes(AttendanceSegment $segment, string $time): int
    {
        if ($time === '') {
            return 0;
        }

        $interval = $this->segmentInterval($segment);

        if (! $interval) {
            return 0;
        }

        [$start, $end] = $interval;
        $threshold = $this->dateTimeFor($this->segmentDate($segment), $time);

        if ($threshold->lte($start)) {
            $threshold = $threshold->addDay();
        }

        $overlapEnd = $end->lt($threshold) ? $end : $threshold;

        return $start->lt($overlapEnd) ? (int) $start->diffInMinutes($overlapEnd) : 0;
    }

    private function clockWindowMinutes(AttendanceSegment $segment, string $startTime, string $endTime): int
    {
        $interval = $this->segmentInterval($segment);

        if (! $interval || $startTime === '' || $endTime === '') {
            return 0;
        }

        [$start, $end] = $interval;
        $minutes = 0;
        $date = $start->startOfDay()->subDay();
        $last = $end->startOfDay();

        while ($date->lte($last)) {
            $windowStart = $this->dateTimeFor($date->toDateString(), $startTime);
            $windowEnd = $this->dateTimeFor($date->toDateString(), $endTime);

            if ($windowEnd->lte($windowStart)) {
                $windowEnd = $windowEnd->addDay();
            }

            $overlapStart = $start->gt($windowStart) ? $start : $windowStart;
            $overlapEnd = $end->lt($windowEnd) ? $end : $windowEnd;

            if ($overlapStart->lt($overlapEnd)) {
                $minutes += (int) $overlapStart->diffInMinutes($overlapEnd);
            }

            $date = $date->addDay();
        }

        return $minutes;
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    private function segmentInterval(AttendanceSegment $segment): ?array
    {
        if (! $segment->clock_in || ! $segment->clock_out) {
            return null;
        }

        $start = $this->dateTimeFor($this->segmentDate($segment), (string) $segment->clock_in);
        $end = $this->dateTimeFor($this->segmentDate($segment), (string) $segment->clock_out);

        if ($end->lte($start)) {
            $end = $end->addDay();
        }

        return [$start, $end];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    private function scheduledInterval(AttendanceSegment $segment): ?array
    {
        if (! $segment->scheduled_start || ! $segment->scheduled_end) {
            return null;
        }

        $start = $this->dateTimeFor($this->segmentDate($segment), (string) $segment->scheduled_start);
        $end = $this->dateTimeFor($this->segmentDate($segment), (string) $segment->scheduled_end);

        if ($end->lte($start)) {
            $end = $end->addDay();
        }

        return [$start, $end];
    }

    private function dateTimeFor(string $date, string $time): CarbonImmutable
    {
        return CarbonImmutable::parse($date.' '.$time);
    }

    private function segmentDate(AttendanceSegment $segment): string
    {
        return CarbonImmutable::parse($segment->getRawOriginal('date'))->toDateString();
    }
}

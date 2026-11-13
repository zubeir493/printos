<?php

namespace App\Services\Hr;

use App\Models\AttendanceDailySummary;
use App\Models\AttendanceSegment;
use App\Models\Employee;
use App\Models\Holiday;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class BuildScheduledAttendance
{
    public function __construct(private ResolveEmployeeWork $work, private WorkTimeIntervals $intervals) {}

    /** @return Collection<int, AttendanceDailySummary> */
    public function handle(Employee $employee, string $from, string $to): Collection
    {
        $employee->load(['workAssignments.payPolicy', 'workAssignments.workSchedule.days.shift', 'workAssignments.workSchedule.days.additionalShifts', 'shiftOverrides.shift', 'shiftOverrides.additionalShifts']);
        $segments = AttendanceSegment::where('employee_id', $employee->id)->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)->get()->groupBy(fn ($segment): string => $segment->date->toDateString());
        $leaves = $employee->leaveRequests()->with('leaveType')->where('status', 'approved')->whereDate('start_date', '<=', $to)->whereDate('end_date', '>=', $from)->get();
        $result = collect();
        $holidays = Holiday::whereDate('date', '>=', $from)->whereDate('date', '<=', $to)->pluck('date')->map(fn ($date): string => CarbonImmutable::parse($date)->toDateString())->flip();
        for ($date = CarbonImmutable::parse($from); $date->lte(CarbonImmutable::parse($to)); $date = $date->addDay()) {
            if ($date->lt($employee->hire_date) || ($employee->termination_date && $date->gt($employee->termination_date))) {
                continue;
            }
            $key = $date->toDateString();
            $work = $this->work->forDate($employee, $key);
            $shift = $work['shift'];
            $shifts = $work['shifts'];
            $holiday = $holidays->has($key);
            $expected = $holiday ? 0 : (int) $shifts->sum('expected_minutes');
            $daySegments = $segments->get($key, collect());
            $actual = $this->intervals->actual($daySegments, $shifts->all(), $key);
            $seconds = array_sum(array_map(fn (array $interval): int => $interval[1] - $interval[0], $actual));
            $worked = (int) floor($seconds / 60);
            $paidLeave = 0;
            $unpaidLeave = 0;
            foreach ($leaves as $leave) {
                if ($key < $leave->start_date->toDateString() || $key > $leave->end_date->toDateString()) {
                    continue;
                }
                $duration = $leave->minutes === null ? $expected : min($expected, (int) round($leave->minutes / max(1, $leave->start_date->diffInDays($leave->end_date) + 1)));
                if ($leave->leaveType->is_paid) {
                    $paidLeave += $duration;
                } else {
                    $unpaidLeave += $duration;
                }
            }
            $paidLeave = min($expected, $paidLeave);
            $unpaidLeave = min($expected - $paidLeave, $unpaidLeave);
            $absent = $daySegments->contains(fn ($segment): bool => strcasecmp((string) $segment->status, 'Absent') === 0);
            $issues = [];
            foreach ($shifts as $periodShift) {
                if ($periodShift->break_minutes > 0 && ! $periodShift->break_paid && (! $periodShift->break_start || ! $periodShift->break_end)) {
                    $issues[] = $key.': break times are missing for '.$periodShift->name.'.';
                }
            }
            if ($absent && $worked > 0) {
                $issues[] = $key.': absence conflicts with recorded work.';
            }
            $late = 0;
            $early = 0;
            $closed = $shifts->isNotEmpty();
            $scheduledIntervals = [];
            foreach ($shifts as $periodShift) {
                [$start, $end] = $this->intervals->scheduled($periodShift, $key);
                $window = [$start->getTimestamp(), $end->getTimestamp()];
                $scheduledIntervals[] = $window;
                $closed = $closed && now()->gt($end->addMinutes($periodShift->capture_after_minutes));
                $periodActual = $this->intervals->intersect($actual, [$window]);
                if ($periodActual !== []) {
                    $paidSchedule = $this->intervals->paidSchedule($periodShift, $key);
                    $periodLate = (int) floor(array_sum(array_map(fn (array $interval): int => max(0, min($interval[1], $periodActual[0][0]) - $interval[0]), $paidSchedule)) / 60);
                    $late += $periodLate <= $periodShift->grace_minutes ? 0 : $periodLate;
                    $early += (int) floor(array_sum(array_map(fn (array $interval): int => max(0, $interval[1] - max($interval[0], $periodActual[array_key_last($periodActual)][1])), $paidSchedule)) / 60);
                }
            }
            $scheduledIntervals = $this->intervals->merge($scheduledIntervals);
            $scheduled = $scheduledIntervals[0] ?? null;
            if ($daySegments->contains(fn ($segment): bool => (bool) $segment->clock_in !== (bool) $segment->clock_out)) {
                $issues[] = $key.': incomplete attendance punch pair.';
            }
            if ($actual !== [] && $actual[array_key_last($actual)][1] > now()->addMinutes(5)->getTimestamp()) {
                $issues[] = $key.': attendance timestamp is in the future.';
            }
            $regularIntervals = $this->intervals->intersect($actual, $scheduledIntervals);
            $regular = min($expected, (int) floor(array_sum(array_map(fn (array $interval): int => $interval[1] - $interval[0], $regularIntervals)) / 60));
            if ($expected > 0 && $closed && ! $absent && $regular === 0 && $paidLeave + $unpaidLeave < $expected) {
                $issues[] = $key.': attendance is missing; review punches or record an approved absence.';
            } elseif ($expected > 0 && $closed && ! $absent && $expected - $regular - $paidLeave - $unpaidLeave > $late + $early) {
                $issues[] = $key.': an unrecorded interval inside the shift needs review.';
            }
            $status = $holiday ? 'holiday' : (! $shift ? 'dayoff' : ($absent ? 'absent' : ($issues !== [] ? 'needs_review' : ($worked ? 'present' : ($paidLeave ? 'paid_leave' : ($unpaidLeave ? 'unpaid_leave' : 'scheduled'))))));
            $summary = AttendanceDailySummary::where('employee_id', $employee->id)->whereDate('date', $key)->first() ?? new AttendanceDailySummary(['employee_id' => $employee->id]);
            $summary->fill(['date' => $key, 'expected_minutes' => $expected, 'worked_minutes' => $regular, 'regular_minutes' => $regular, 'late_minutes' => $late + $early, 'overtime_minutes' => max(0, $worked - $regular), 'holiday_minutes' => $holiday || ! $shift ? $worked : 0, 'night_minutes' => 0, 'absence_minutes' => $absent ? max($unpaidLeave, $expected - $paidLeave) : $unpaidLeave, 'paid_leave_minutes' => $paidLeave, 'unpaid_leave_minutes' => $unpaidLeave, 'status' => $status, 'calculation_snapshot' => ['version' => 2, 'issues' => $issues, 'work_intervals' => $actual, 'scheduled_interval' => $scheduled, 'scheduled_intervals' => $scheduledIntervals, 'shifts' => $shifts->map->attributesToArray()->all(), 'total_worked_minutes' => $worked, 'assignment_id' => $work['assignment']->id, 'override_id' => $work['override_id'], 'shift' => $shift?->attributesToArray(), 'policy' => $work['policy']->attributesToArray(), 'attendance_segment_ids' => $daySegments->pluck('id')->all(), 'early_minutes' => $early]]);
            $summary->save();
            $result->push($summary);
        }

        return $result;
    }
}

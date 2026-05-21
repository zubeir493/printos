<?php

namespace App\Services\Hr;

use App\Models\AttendanceDailySummary;
use App\Models\AttendanceSegment;
use Illuminate\Support\Collection;

class RebuildAttendanceDailySummaries
{
    /**
     * @param  Collection<int, AttendanceSegment>  $segments
     */
    public function forSegments(Collection $segments): void
    {
        $segments
            ->groupBy(fn (AttendanceSegment $segment): string => $segment->employee_id.'|'.$segment->date->toDateString())
            ->each(fn (Collection $daySegments): mixed => $this->rebuildDay($daySegments));
    }

    /**
     * @param  array<int>  $employeeIds
     */
    public function forPeriod(array $employeeIds, string $periodStart, string $periodEnd): void
    {
        AttendanceDailySummary::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('date', '>=', $periodStart)
            ->whereDate('date', '<=', $periodEnd)
            ->delete();

        $this->forSegments(
            AttendanceSegment::query()
                ->whereIn('employee_id', $employeeIds)
                ->whereDate('date', '>=', $periodStart)
                ->whereDate('date', '<=', $periodEnd)
                ->orderBy('date')
                ->get()
        );
    }

    /**
     * @param  Collection<int, AttendanceSegment>  $segments
     */
    private function rebuildDay(Collection $segments): void
    {
        $first = $segments->first();
        $status = $this->dailyStatus($segments);
        $expectedMinutes = (int) $segments->sum(fn (AttendanceSegment $segment): int => $this->expectedSegmentMinutes($segment));
        $workedMinutes = (int) $segments->sum('worked_minutes');
        $overtimeMinutes = (int) $segments->sum('overtime_minutes');
        $lateAndEarlyMinutes = (int) $segments->sum('late_minutes') + (int) $segments->sum('early_minutes');
        $isHolidayOrDayoff = $segments->contains(fn (AttendanceSegment $segment): bool => $this->isStatus($segment, 'Holiday') || $this->isStatus($segment, 'Dayoff'));

        AttendanceDailySummary::updateOrCreate(
            [
                'employee_id' => $first->employee_id,
                'date' => $first->date->toDateString(),
            ],
            [
                'expected_minutes' => $expectedMinutes,
                'worked_minutes' => $workedMinutes,
                'regular_minutes' => min($workedMinutes, $expectedMinutes),
                'late_minutes' => $lateAndEarlyMinutes,
                'overtime_minutes' => $overtimeMinutes,
                'holiday_minutes' => $isHolidayOrDayoff ? $workedMinutes + $overtimeMinutes : 0,
                'night_minutes' => 0,
                'absence_minutes' => $segments->contains(fn (AttendanceSegment $segment): bool => $this->isStatus($segment, 'Absent')) ? max(480, $expectedMinutes) : 0,
                'status' => $status,
                'calculation_snapshot' => [
                    'attendance_segment_ids' => $segments->pluck('id')->all(),
                    'schedules' => $segments->pluck('schedule_name')->filter()->unique()->values()->all(),
                    'early_minutes' => (int) $segments->sum('early_minutes'),
                ],
            ],
        );
    }

    private function dailyStatus(Collection $segments): string
    {
        if ($segments->contains(fn (AttendanceSegment $segment): bool => $this->isStatus($segment, 'Absent'))) {
            return 'absent';
        }

        if ($segments->contains(fn (AttendanceSegment $segment): bool => $this->isStatus($segment, 'Holiday'))) {
            return 'holiday';
        }

        if ($segments->contains(fn (AttendanceSegment $segment): bool => $this->isStatus($segment, 'Dayoff'))) {
            return 'dayoff';
        }

        if ($segments->contains(fn (AttendanceSegment $segment): bool => $this->isStatus($segment, 'Late') || $this->isStatus($segment, 'Early'))) {
            return 'partial';
        }

        return 'present';
    }

    private function expectedSegmentMinutes(AttendanceSegment $segment): int
    {
        if ($this->isStatus($segment, 'Holiday') || $this->isStatus($segment, 'Dayoff')) {
            return 0;
        }

        if ($this->isStatus($segment, 'Absent')) {
            return max(480, (int) round((float) $segment->day_fraction * 480));
        }

        return (int) round((float) $segment->day_fraction * 480);
    }

    private function isStatus(AttendanceSegment $segment, string $status): bool
    {
        return strcasecmp((string) $segment->status, $status) === 0
            || strcasecmp((string) $segment->exception, $status) === 0;
    }
}

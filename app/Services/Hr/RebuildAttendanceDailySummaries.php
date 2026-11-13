<?php

namespace App\Services\Hr;

use App\Models\AttendanceDailySummary;
use App\Models\AttendanceSegment;
use App\Models\Employee;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class RebuildAttendanceDailySummaries
{
    public function __construct(private BuildScheduledAttendance $attendance) {}

    /** @param Collection<int, AttendanceSegment> $segments */
    public function forSegments(Collection $segments): void
    {
        foreach ($segments->groupBy(fn (AttendanceSegment $segment): string => $segment->employee_id.'|'.$segment->date->toDateString()) as $daySegments) {
            $first = $daySegments->first();
            $this->forPeriod([$first->employee_id], $first->date->toDateString(), $first->date->toDateString());
        }
    }

    /** @param list<int> $employeeIds */
    public function forPeriod(array $employeeIds, string $periodStart, string $periodEnd): void
    {
        foreach (Employee::whereIn('id', $employeeIds)->get() as $employee) {
            try {
                $this->attendance->handle($employee, $periodStart, $periodEnd);
            } catch (ValidationException $exception) {
                $segments = $employee->attendanceSegments()->whereDate('date', '>=', $periodStart)->whereDate('date', '<=', $periodEnd)->get()->groupBy(fn ($segment): string => $segment->date->toDateString());
                foreach ($segments as $date => $daySegments) {
                    $summary = AttendanceDailySummary::where('employee_id', $employee->id)->whereDate('date', $date)->first() ?? new AttendanceDailySummary(['employee_id' => $employee->id]);
                    $summary->fill(['date' => $date, 'expected_minutes' => 0, 'worked_minutes' => 0, 'regular_minutes' => 0, 'late_minutes' => 0, 'overtime_minutes' => 0, 'holiday_minutes' => 0, 'night_minutes' => 0, 'absence_minutes' => 0, 'paid_leave_minutes' => 0, 'unpaid_leave_minutes' => 0, 'status' => 'needs_review', 'calculation_snapshot' => ['version' => 2, 'issues' => [$exception->getMessage()], 'attendance_segment_ids' => $daySegments->modelKeys()]])->save();
                }
            }
        }
    }
}

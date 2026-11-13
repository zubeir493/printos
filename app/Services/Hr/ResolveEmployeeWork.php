<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\EmployeeShiftOverride;
use App\Models\EmployeeWorkAssignment;
use App\Models\PayPolicy;
use App\Models\Shift;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class ResolveEmployeeWork
{
    /** @return array{date: string, assignment: EmployeeWorkAssignment, policy: PayPolicy, shift: ?Shift, shifts: Collection<int, Shift>, override_id: ?int} */
    public function forDate(Employee $employee, string $date): array
    {
        $date = CarbonImmutable::parse($date)->toDateString();
        $assignment = $employee->relationLoaded('workAssignments')
            ? $employee->workAssignments->first(fn ($assignment): bool => $assignment->effective_from->toDateString() <= $date && (! $assignment->effective_to || $assignment->effective_to->toDateString() >= $date))
            : $employee->workAssignments()->with(['payPolicy', 'workSchedule.days.shift', 'workSchedule.days.additionalShifts'])
                ->whereDate('effective_from', '<=', $date)->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date))->first();
        if (! $assignment) {
            throw ValidationException::withMessages(['work_assignment' => 'Assign a work schedule and pay policy to '.$employee->employee_id.' for '.$date.'.']);
        }
        $override = $employee->relationLoaded('shiftOverrides')
            ? $employee->shiftOverrides->first(fn ($override): bool => $override->date->toDateString() === $date)
            : EmployeeShiftOverride::with('shift', 'additionalShifts')->where('employee_id', $employee->id)->whereDate('date', $date)->first();
        $day = $assignment->workSchedule->days->firstWhere('day_of_week', CarbonImmutable::parse($date)->dayOfWeek);

        $shifts = ($override ? $override->resolvedShifts() : $day?->resolvedShifts()) ?? (new Shift)->newCollection();

        return ['date' => $date, 'assignment' => $assignment, 'policy' => $assignment->payPolicy, 'shift' => $shifts->first(), 'shifts' => $shifts, 'override_id' => $override?->id];
    }

    /** @return array{date: string, assignment: EmployeeWorkAssignment, policy: PayPolicy, shift: ?Shift, shifts: Collection<int, Shift>, override_id: ?int} */
    public function forPunch(Employee $employee, CarbonImmutable $time, ?int $direction = null): array
    {
        $time = $time->setTimezone(config('app.timezone'));
        $candidates = [];
        foreach ([-1, 0, 1] as $offset) {
            try {
                $work = $this->forDate($employee, $time->addDays($offset)->toDateString());
            } catch (ValidationException) {
                continue;
            }
            foreach ($work['shifts'] as $shift) {
                [$start, $end] = app(WorkTimeIntervals::class)->scheduled($shift, $work['date']);
                if ($time->betweenIncluded($start->subMinutes($shift->capture_before_minutes), $end->addMinutes($shift->capture_after_minutes))) {
                    $preferredBoundary = in_array($direction, [0, 3, 4], true) ? $start : $end;
                    $candidates[] = ['work' => $work, 'boundary' => $direction !== null && $time->eq($preferredBoundary), 'inside' => $time->betweenIncluded($start, $end), 'distance' => min(abs($time->getTimestamp() - $start->getTimestamp()), abs($time->getTimestamp() - $end->getTimestamp()))];
                }
            }
        }
        usort($candidates, fn (array $left, array $right): int => ($right['boundary'] <=> $left['boundary']) ?: (($right['inside'] <=> $left['inside']) ?: ($left['distance'] <=> $right['distance'])));

        return $candidates[0]['work'] ?? $this->forDate($employee, $time->toDateString());
    }
}

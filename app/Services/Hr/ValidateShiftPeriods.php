<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\Shift;
use App\Models\WorkSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ValidateShiftPeriods
{
    public function forShift(Shift $shift): void
    {
        $usesShift = fn ($query) => $query->where('shift_id', $shift->id)->orWhereHas('additionalShifts', fn ($query) => $query->whereKey($shift->id));
        $schedules = WorkSchedule::whereHas('days', $usesShift)->get();
        foreach ($schedules as $schedule) {
            $this->forSchedule($schedule, 'start_time');
        }
        $employees = Employee::whereHas('workAssignments', fn ($query) => $query->whereIn('work_schedule_id', $schedules->modelKeys()))
            ->orWhereHas('shiftOverrides', $usesShift)->get();
        foreach ($employees as $employee) {
            $dates = $employee->workAssignments()->get()->flatMap(fn ($assignment): array => [$assignment->effective_from?->toDateString(), $assignment->effective_to?->toDateString()])
                ->merge($employee->shiftOverrides()->pluck('date')->map->toDateString())->filter()->unique();
            foreach ($dates as $date) {
                $this->forEmployeeDate($employee, $date, 'start_time');
            }
        }
        PayrollRun::where('status', 'draft')->whereHas('employees', fn ($query) => $query->whereIn('employee_id', $employees->modelKeys()))
            ->update(['attendance_reviewed_at' => null, 'attendance_reviewed_by' => null, 'attendance_review_note' => null]);
    }

    /** @param Collection<int, Shift> $shifts */
    public function forDay(Collection $shifts, string $field = 'shift_ids'): void
    {
        $periods = $shifts->map(function (Shift $shift): array {
            [$start, $end] = app(WorkTimeIntervals::class)->scheduled($shift, '2026-01-05');

            return [$start->getTimestamp(), $end->getTimestamp(), $shift->name];
        })->sortBy(fn (array $period): int => $period[0])->values();
        for ($index = 1; $index < $periods->count(); $index++) {
            if ($periods[$index][0] < $periods[$index - 1][1]) {
                throw ValidationException::withMessages([$field => $periods[$index - 1][2].' overlaps '.$periods[$index][2].'.']);
            }
        }
    }

    public function forSchedule(WorkSchedule $schedule, string $field = 'days'): void
    {
        $schedule->load('days.shift', 'days.additionalShifts');
        $this->validateWeek($schedule->days->map(fn ($day): array => ['day_of_week' => $day->day_of_week, 'shifts' => $day->resolvedShifts()])->all(), $field);
    }

    /** @param array<string|int, array{day_of_week: int|string, shift_ids: list<int|string>}> $days */
    public function forPattern(array $days, string $field): void
    {
        if (collect($days)->pluck('day_of_week')->map(fn ($day): int => (int) $day)->sort()->values()->all() !== range(0, 6)) {
            throw ValidationException::withMessages([$field => 'Include each weekday once.']);
        }
        $shifts = Shift::whereIn('id', collect($days)->pluck('shift_ids')->flatten()->unique())->get()->keyBy('id');
        $this->validateWeek(collect($days)->map(fn (array $day): array => ['day_of_week' => (int) $day['day_of_week'], 'shifts' => collect($day['shift_ids'])->map(fn ($id) => $shifts->get($id))->filter()])->all(), $field);
    }

    /** @param array<string|int, array{day_of_week: int, shifts: Collection<int, Shift>}> $days */
    private function validateWeek(array $days, string $field): void
    {
        $periods = [];
        $week = CarbonImmutable::parse('2026-01-05');
        foreach ($days as $day) {
            $offset = ($day['day_of_week'] + 6) % 7;
            foreach ($day['shifts'] as $shift) {
                [$start, $end] = app(WorkTimeIntervals::class)->scheduled($shift, $week->addDays($offset)->toDateString());
                foreach ([-7, 0, 7] as $weekOffset) {
                    $periods[] = [$start->addDays($weekOffset)->getTimestamp(), $end->addDays($weekOffset)->getTimestamp(), $shift->name];
                }
            }
        }
        $this->assertNoOverlap($periods, $field);
    }

    public function forEmployeeDate(Employee $employee, string $date, string $field = 'shift_ids'): void
    {
        $periods = [];
        $date = CarbonImmutable::parse($date);
        foreach ([-1, 0, 1] as $offset) {
            $workDate = $date->addDays($offset)->toDateString();
            try {
                $work = app(ResolveEmployeeWork::class)->forDate($employee, $workDate);
            } catch (ValidationException) {
                continue;
            }
            foreach ($work['shifts'] as $shift) {
                [$start, $end] = app(WorkTimeIntervals::class)->scheduled($shift, $workDate);
                $periods[] = [$start->getTimestamp(), $end->getTimestamp(), $shift->name.' ('.$workDate.')'];
            }
        }
        $this->assertNoOverlap($periods, $field);
    }

    /** @param list<array{int, int, string}> $periods */
    private function assertNoOverlap(array $periods, string $field): void
    {
        usort($periods, fn (array $left, array $right): int => $left[0] <=> $right[0]);
        for ($index = 1; $index < count($periods); $index++) {
            if ($periods[$index][0] < $periods[$index - 1][1]) {
                throw ValidationException::withMessages([$field => $periods[$index - 1][2].' overlaps '.$periods[$index][2].' across workdays.']);
            }
        }
    }
}

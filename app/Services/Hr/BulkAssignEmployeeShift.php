<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\PayrollRun;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BulkAssignEmployeeShift
{
    /**
     * @param  Collection<int, Employee>  $employees
     * @param  array<string, mixed>  $data
     */
    public function handle(Collection $employees, array $data): int
    {
        if ($employees->isEmpty()) {
            throw ValidationException::withMessages(['shift_id' => 'Select at least one employee.']);
        }

        $data = Validator::make($data, [
            'shift_id' => ['required', 'integer', 'exists:shifts,id'],
            'workdays' => ['required', 'array', 'min:1', 'max:7'],
            'workdays.*' => ['required', 'integer', 'between:0,6', 'distinct'],
            'effective_from' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
        ])->validate();
        $data['reason'] ??= null;

        return DB::transaction(function () use ($employees, $data): int {
            $employees = Employee::query()->whereKey($employees->pluck('id'))->orderBy('id')->lockForUpdate()->get();
            foreach ($employees as $employee) {
                Gate::authorize('update', $employee);
            }
            $days = collect(range(0, 6))->map(fn (int $day): array => [
                'day_of_week' => $day,
                'shift_ids' => in_array($day, array_map('intval', $data['workdays']), true) ? [(int) $data['shift_id']] : [],
            ])->all();
            $schedule = app(ResolveAssignmentPattern::class)->handle($days);
            $from = CarbonImmutable::parse($data['effective_from'])->toDateString();

            foreach ($employees as $employee) {
                try {
                    Validator::make(['effective_from' => $from], [
                        'effective_from' => ['after_or_equal:'.$employee->hire_date->toDateString(), ...($employee->termination_date ? ['before_or_equal:'.$employee->termination_date->toDateString()] : [])],
                    ])->validate();
                    if (PayrollRun::whereIn('status', ['approved', 'paid'])->whereDate('period_end', '>=', $from)
                        ->whereHas('employees', fn ($query) => $query->where('employee_id', $employee->id))->exists()) {
                        throw ValidationException::withMessages(['effective_from' => 'Choose a date after the approved payroll period.']);
                    }

                    $current = $employee->workAssignments()->whereDate('effective_from', '<', $from)
                        ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $from))->first();
                    if ($current) {
                        app(UpdateEmployeeWorkAssignment::class)->handle($current, ['effective_to' => CarbonImmutable::parse($from)->subDay()->toDateString()]);
                    }
                    $future = $employee->workAssignments()->whereDate('effective_from', '>=', $from)->orderBy('effective_from')->get();
                    foreach ($future as $assignment) {
                        app(UpdateEmployeeWorkAssignment::class)->handle($assignment, ['work_schedule_id' => $schedule->id, 'reason' => $data['reason']]);
                    }
                    if (! $future->first()?->effective_from->isSameDay($from)) {
                        app(AssignEmployeeWork::class)->handleCompanyRules(
                            $employee, $schedule->id, $from, $data['reason'],
                            $future->first()?->effective_from->subDay()->toDateString(),
                        );
                    }
                    PayrollRun::where('status', 'draft')->whereDate('period_end', '>=', $from)
                        ->whereHas('employees', fn ($query) => $query->where('employee_id', $employee->id))
                        ->update(['attendance_reviewed_at' => null, 'attendance_reviewed_by' => null, 'attendance_review_note' => null]);
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn (array $messages, string $field): array => [
                        match ($field) {
                            'work_schedule_id', 'days' => 'shift_id', 'pay_policy_id' => 'effective_from', default => $field
                        } => array_map(fn (string $message): string => $employee->full_name.': '.$message, $messages),
                    ])->all());
                }
            }

            return $employees->count();
        }, 3);
    }
}

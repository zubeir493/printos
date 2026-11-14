<?php

namespace App\Models;

use App\Services\Hr\ValidateShiftPeriods;
use Database\Factories\EmployeeWorkAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class EmployeeWorkAssignment extends Model
{
    /** @use HasFactory<EmployeeWorkAssignmentFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['effective_from' => 'immutable_date', 'effective_to' => 'immutable_date'];
    }

    public function save(array $options = []): bool
    {
        return DB::transaction(function () use ($options): bool {
            $employee = Employee::query()->lockForUpdate()->findOrFail($this->employee_id);
            $this->load(['workSchedule', 'payPolicy']);
            Validator::make($this->attributesToArray(), [
                'work_schedule_id' => ['required', 'exists:work_schedules,id'], 'pay_policy_id' => ['required', 'exists:pay_policies,id'],
                'effective_from' => ['required', 'date', 'after_or_equal:'.$employee->hire_date->toDateString()],
                'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'], 'reason' => ['nullable', 'string'],
            ])->validate();
            $overlap = self::where('employee_id', $this->employee_id)->when($this->exists, fn ($query) => $query->whereKeyNot($this->id))
                ->whereDate('effective_from', '<=', $this->effective_to?->toDateString() ?? '9999-12-31')
                ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $this->effective_from->toDateString()))->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['effective_from' => 'This employee already has an assignment in these dates. End that assignment first.']);
            }
            if ($this->workSchedule->days()->count() !== 7) {
                throw ValidationException::withMessages(['work_schedule_id' => 'Configure all seven weekdays, including rest days, before assigning this schedule.']);
            }
            app(ValidateShiftPeriods::class)->forSchedule($this->workSchedule);
            if ((! $this->exists || $this->isDirty('pay_policy_id')) && ! $this->payPolicy->is_active) {
                throw ValidationException::withMessages(['pay_policy_id' => 'Choose an active pay policy.']);
            }

            $saved = parent::save($options);
            $dates = collect([$this->effective_from->toDateString(), $this->effective_to?->toDateString()])->filter();
            $dates = $dates->merge($employee->shiftOverrides()->whereDate('date', '>=', $this->effective_from)
                ->when($this->effective_to, fn ($query) => $query->whereDate('date', '<=', $this->effective_to))->pluck('date')->map->toDateString())->unique();
            foreach ($dates as $date) {
                app(ValidateShiftPeriods::class)->forEmployeeDate($employee, $date, 'work_schedule_id');
            }

            return $saved;
        });
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function workSchedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class);
    }

    public function payPolicy(): BelongsTo
    {
        return $this->belongsTo(PayPolicy::class);
    }
}

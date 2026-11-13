<?php

namespace App\Models;

use App\Services\Hr\ValidateShiftPeriods;
use Database\Factories\EmployeeShiftOverrideFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class EmployeeShiftOverride extends Model
{
    /** @use HasFactory<EmployeeShiftOverrideFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /** @var list<int>|null */
    private ?array $pendingShiftIds = null;

    public function save(array $options = []): bool
    {
        return DB::transaction(function () use ($options): bool {
            $employee = Employee::query()->lockForUpdate()->findOrFail($this->employee_id);
            $dates = collect([$this->getRawOriginal('date'), $this->date?->toDateString()])->filter()->unique()->values();
            if (PayrollRun::where('status', '!=', 'draft')->whereHas('employees', fn ($query) => $query->where('employee_id', $employee->id))
                ->where(function ($query) use ($dates): void {
                    foreach ($dates as $date) {
                        $query->orWhere(fn ($query) => $query->whereDate('period_start', '<=', $date)->whereDate('period_end', '>=', $date));
                    }
                })->exists()) {
                throw ValidationException::withMessages(['date' => 'This date belongs to finalized payroll.']);
            }
            $saved = parent::save($options);
            foreach ($dates as $date) {
                app(ValidateShiftPeriods::class)->forEmployeeDate($employee, $date);
            }
            PayrollRun::where('status', 'draft')->whereHas('employees', fn ($query) => $query->where('employee_id', $employee->id))
                ->whereDate('period_start', '<=', $dates->max())->whereDate('period_end', '>=', $dates->min())
                ->update(['attendance_reviewed_at' => null, 'attendance_reviewed_by' => null]);

            return $saved;
        });
    }

    /** @param list<int|string> $value */
    public function setShiftIdsAttribute(array $value): void
    {
        Validator::make(['shift_ids' => $value], ['shift_ids' => ['array'], 'shift_ids.*' => ['integer', 'distinct', 'exists:shifts,id']])->validate();
        $this->pendingShiftIds = array_values(array_map('intval', $value));
        $this->shift_id = $this->pendingShiftIds[0] ?? null;
    }

    /** @return list<int> */
    public function getShiftIdsAttribute(): array
    {
        return $this->pendingShiftIds ?? $this->resolvedShifts()->modelKeys();
    }

    /** @return Collection<int, Shift> */
    public function resolvedShifts(): Collection
    {
        if ($this->pendingShiftIds !== null) {
            return Shift::whereIn('id', $this->pendingShiftIds)->orderBy('start_time')->get();
        }
        $this->loadMissing('shift', 'additionalShifts');

        return (new Shift)->newCollection([...($this->shift ? [$this->shift] : []), ...$this->additionalShifts])->unique('id')->sortBy('start_time')->values();
    }

    public function additionalShifts(): BelongsToMany
    {
        return $this->belongsToMany(Shift::class, 'employee_shift_override_shifts')->withPivot('position')->orderByPivot('position');
    }

    protected function casts(): array
    {
        return ['date' => 'immutable_date'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $override): void {
            Validator::make($override->attributesToArray(), ['employee_id' => ['required', 'exists:employees,id'], 'date' => ['required', 'date'], 'shift_id' => ['nullable', 'exists:shifts,id'], 'reason' => ['required', 'string']])->validate();
            $employee = $override->employee()->firstOrFail();
            if ($override->date->lt($employee->hire_date) || ($employee->termination_date && $override->date->gt($employee->termination_date))) {
                throw ValidationException::withMessages(['date' => 'An override must fall within employment dates.']);
            }
            $override->approved_by ??= auth()->id();
            app(ValidateShiftPeriods::class)->forDay($override->resolvedShifts());
        });
        static::saved(function (self $override): void {
            if ($override->pendingShiftIds !== null) {
                $override->additionalShifts()->sync(collect(array_slice($override->pendingShiftIds, 1))->mapWithKeys(fn (int $id, int $position): array => [$id => ['position' => $position]])->all());
                $override->pendingShiftIds = null;
                $override->unsetRelation('shift')->unsetRelation('additionalShifts');
            }
        });
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}

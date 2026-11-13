<?php

namespace App\Models;

use App\Services\Hr\ValidateShiftPeriods;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class WorkScheduleDay extends Model
{
    protected $guarded = ['id'];

    /** @var list<int>|null */
    private ?array $pendingShiftIds = null;

    public function save(array $options = []): bool
    {
        return DB::transaction(fn (): bool => parent::save($options));
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
        return $this->belongsToMany(Shift::class, 'work_schedule_day_shifts')->withPivot('position')->orderByPivot('position');
    }

    protected static function booted(): void
    {
        static::saving(function (self $day): void {
            Validator::make($day->attributesToArray(), ['day_of_week' => ['required', 'integer', 'between:0,6'], 'work_schedule_id' => ['required', 'exists:work_schedules,id'], 'shift_id' => ['nullable', 'exists:shifts,id']])->validate();
            if ($day->workSchedule->assignments()->exists()) {
                throw ValidationException::withMessages(['days' => 'Assigned schedules are locked. Duplicate the schedule first.']);
            }
            app(ValidateShiftPeriods::class)->forDay($day->resolvedShifts());
        });
        static::saved(function (self $day): void {
            if ($day->pendingShiftIds !== null) {
                $day->additionalShifts()->sync(collect(array_slice($day->pendingShiftIds, 1))->mapWithKeys(fn (int $id, int $position): array => [$id => ['position' => $position]])->all());
                $day->pendingShiftIds = null;
                $day->unsetRelation('shift')->unsetRelation('additionalShifts');
            }
        });
        static::deleting(function (self $day): void {
            if ($day->workSchedule->assignments()->exists()) {
                throw ValidationException::withMessages(['days' => 'Assigned schedules are locked.']);
            }
        });
    }

    public function workSchedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}

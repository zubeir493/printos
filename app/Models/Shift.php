<?php

namespace App\Models;

use App\Services\Hr\ValidateShiftPeriods;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'break_start', 'break_end', 'break_paid', 'capture_before_minutes', 'capture_after_minutes',
        'name',
        'start_time',
        'end_time',
        'break_minutes',
        'grace_minutes',
        'expected_minutes',
        'is_night_shift',
        'overtime_eligible',
    ];

    protected $attributes = ['break_minutes' => 0, 'grace_minutes' => 0, 'break_paid' => false, 'capture_before_minutes' => 120, 'capture_after_minutes' => 240, 'overtime_eligible' => true, 'is_night_shift' => false];

    public function save(array $options = []): bool
    {
        return DB::transaction(fn (): bool => parent::save($options));
    }

    protected static function booted(): void
    {
        static::saving(function (self $shift): void {
            foreach (['start_time', 'end_time', 'break_start', 'break_end'] as $field) {
                if ($shift->$field) {
                    $shift->$field = CarbonImmutable::parse('2000-01-01 '.$shift->$field)->format('H:i:s');
                }
            }
            Validator::make($shift->attributesToArray(), [
                'name' => ['required', 'string'], 'start_time' => ['required', 'date_format:H:i:s'], 'end_time' => ['required', 'date_format:H:i:s', 'different:start_time'],
                'break_minutes' => ['required', 'integer', 'between:0,480'], 'grace_minutes' => ['required', 'integer', 'between:0,120'],
                'capture_before_minutes' => ['required', 'integer', 'between:0,720'], 'capture_after_minutes' => ['required', 'integer', 'between:0,720'],
                'break_start' => ['nullable', 'required_with:break_end', 'date_format:H:i:s'], 'break_end' => ['nullable', 'required_with:break_start', 'date_format:H:i:s'],
            ])->validate();
            $start = CarbonImmutable::parse('2000-01-01 '.$shift->start_time);
            $end = CarbonImmutable::parse('2000-01-01 '.$shift->end_time);
            if ($end->lte($start)) {
                $end = $end->addDay();
            }
            if ($shift->isDirty(['break_start', 'break_end']) && ! $shift->break_start && ! $shift->break_end) {
                $shift->break_minutes = 0;
            }
            if ($shift->break_start && $shift->break_end) {
                $breakStart = CarbonImmutable::parse('2000-01-01 '.$shift->break_start);
                if ($breakStart->lt($start)) {
                    $breakStart = $breakStart->addDay();
                }
                $breakEnd = CarbonImmutable::parse($breakStart->toDateString().' '.$shift->break_end);
                if ($breakEnd->lte($breakStart)) {
                    $breakEnd = $breakEnd->addDay();
                }
                if ($breakStart->lt($start) || $breakEnd->gt($end)) {
                    throw ValidationException::withMessages(['break_start' => 'The break must be inside the shift.']);
                }
                $shift->break_minutes = (int) $breakStart->diffInMinutes($breakEnd);
            }
            $shift->expected_minutes = (int) $start->diffInMinutes($end) - ($shift->break_paid ? 0 : (int) $shift->break_minutes);
            $shift->is_night_shift = $shift->end_time < $shift->start_time;
            if ($shift->expected_minutes <= 0) {
                throw ValidationException::withMessages(['break_minutes' => 'The shift must contain paid working time.']);
            }
        });

        static::updated(fn (self $shift) => app(ValidateShiftPeriods::class)->forShift($shift));
    }

    protected function casts(): array
    {
        return [
            'break_paid' => 'boolean',
            'break_minutes' => 'integer',
            'grace_minutes' => 'integer',
            'expected_minutes' => 'integer',
            'is_night_shift' => 'boolean',
            'overtime_eligible' => 'boolean',
        ];
    }

    public function attendanceSegments(): HasMany
    {
        return $this->hasMany(AttendanceSegment::class);
    }

    public function scheduleDays(): HasMany
    {
        return $this->hasMany(WorkScheduleDay::class);
    }

    public function shiftOverrides(): HasMany
    {
        return $this->hasMany(EmployeeShiftOverride::class);
    }

    public function additionalScheduleDays(): BelongsToMany
    {
        return $this->belongsToMany(WorkScheduleDay::class, 'work_schedule_day_shifts');
    }

    public function additionalOverrides(): BelongsToMany
    {
        return $this->belongsToMany(EmployeeShiftOverride::class, 'employee_shift_override_shifts');
    }

    public function isAssigned(): bool
    {
        return WorkScheduleDay::where(fn ($query) => $query->where('shift_id', $this->id)->orWhereHas('additionalShifts', fn ($shifts) => $shifts->whereKey($this->id)))->whereHas('workSchedule.assignments')->exists()
            || EmployeeShiftOverride::where(fn ($query) => $query->where('shift_id', $this->id)->orWhereHas('additionalShifts', fn ($shifts) => $shifts->whereKey($this->id)))->exists();
    }
}

<?php

namespace App\Models;

use Database\Factories\WorkScheduleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class WorkSchedule extends Model
{
    /** @use HasFactory<WorkScheduleFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    public function days(): HasMany
    {
        return $this->hasMany(WorkScheduleDay::class)->orderByRaw('CASE WHEN day_of_week = 0 THEN 7 ELSE day_of_week END');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeeWorkAssignment::class);
    }

    protected static function booted(): void
    {
        static::updating(function (self $schedule): void {
            if ($schedule->assignments()->exists()) {
                throw ValidationException::withMessages(['name' => 'An assigned schedule is locked. Duplicate it to make a new schedule.']);
            }
        });
    }
}

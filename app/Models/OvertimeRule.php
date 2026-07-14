<?php

namespace App\Models;

use Database\Factories\OvertimeRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OvertimeRule extends Model
{
    /** @use HasFactory<OvertimeRuleFactory> */
    use HasFactory;

    public const BASIS_ATTENDANCE_OVERTIME = 'attendance_overtime';

    public const BASIS_BEFORE_SHIFT = 'before_shift';

    public const BASIS_AFTER_SHIFT = 'after_shift';

    public const BASIS_AFTER_CLOCK_TIME = 'after_clock_time';

    public const BASIS_BEFORE_CLOCK_TIME = 'before_clock_time';

    public const BASIS_TIME_WINDOW = 'time_window';

    public const BASIS_WORKED_DAY = 'worked_day';

    public const BASIS_MANUAL = 'manual';

    public const DAY_REGULAR = 'regular';

    public const DAY_WEEKEND_DAYOFF = 'weekend_dayoff';

    public const DAY_HOLIDAY = 'holiday';

    protected $fillable = [
        'name',
        'code',
        'minutes_basis',
        'applies_on_days',
        'multiplier',
        'hourly_rate',
        'minimum_minutes',
        'rounding_increment_minutes',
        'window_start_time',
        'window_end_time',
        'priority',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'multiplier' => 'decimal:4',
            'hourly_rate' => 'decimal:4',
            'applies_on_days' => 'array',
            'minimum_minutes' => 'integer',
            'rounding_increment_minutes' => 'integer',
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function overtimeEntries(): HasMany
    {
        return $this->hasMany(PayrollOvertimeEntry::class);
    }

    /**
     * @return array<string, string>
     */
    public static function minutesBasisOptions(): array
    {
        return [
            self::BASIS_ATTENDANCE_OVERTIME => 'Attendance overtime minutes',
            self::BASIS_BEFORE_SHIFT => 'Before scheduled shift',
            self::BASIS_AFTER_SHIFT => 'After scheduled shift',
            self::BASIS_AFTER_CLOCK_TIME => 'After a clock time',
            self::BASIS_BEFORE_CLOCK_TIME => 'Before a clock time',
            self::BASIS_TIME_WINDOW => 'Inside a clock window',
            self::BASIS_WORKED_DAY => 'All worked minutes that day',
            self::BASIS_MANUAL => 'Manual only',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function dayOptions(): array
    {
        return [
            self::DAY_REGULAR => 'Regular work days',
            self::DAY_WEEKEND_DAYOFF => 'Weekends / day off',
            self::DAY_HOLIDAY => 'Holidays',
        ];
    }

    public function appliesToDay(string $dayType): bool
    {
        return in_array($dayType, $this->applies_on_days ?: [self::DAY_REGULAR], true);
    }
}

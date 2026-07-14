<?php

namespace Database\Seeders;

use App\Models\OvertimeRule;
use Illuminate\Database\Seeder;

class OvertimeRuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([
            ['Regular overtime', 'regular_overtime', OvertimeRule::BASIS_ATTENDANCE_OVERTIME, [OvertimeRule::DAY_REGULAR], 1.5, null, 30, 15, null, null, 40],
            ['Before shift overtime', 'before_shift', OvertimeRule::BASIS_BEFORE_SHIFT, [OvertimeRule::DAY_REGULAR], 1.5, null, 30, 15, null, null, 50],
            ['After shift overtime', 'after_shift', OvertimeRule::BASIS_AFTER_SHIFT, [OvertimeRule::DAY_REGULAR], 1.5, null, 30, 15, null, null, 50],
            ['Night window overtime', 'night_overtime', OvertimeRule::BASIS_TIME_WINDOW, [OvertimeRule::DAY_REGULAR], 1.75, null, 30, 15, '22:00:00', '06:00:00', 30],
            ['Weekend / day off overtime', 'weekend_dayoff_overtime', OvertimeRule::BASIS_WORKED_DAY, [OvertimeRule::DAY_WEEKEND_DAYOFF], 2.0, null, 30, 15, null, null, 20],
            ['Holiday overtime', 'holiday_overtime', OvertimeRule::BASIS_WORKED_DAY, [OvertimeRule::DAY_HOLIDAY], 2.0, null, 30, 15, null, null, 10],
            ['Manual overtime', 'manual_overtime', OvertimeRule::BASIS_MANUAL, [OvertimeRule::DAY_REGULAR, OvertimeRule::DAY_WEEKEND_DAYOFF, OvertimeRule::DAY_HOLIDAY], 1.0, null, 0, 1, null, null, 100],
        ] as [$name, $code, $minutesBasis, $appliesOnDays, $multiplier, $hourlyRate, $minimumMinutes, $roundingIncrement, $windowStart, $windowEnd, $priority]) {
            OvertimeRule::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'minutes_basis' => $minutesBasis,
                    'applies_on_days' => $appliesOnDays,
                    'multiplier' => $multiplier,
                    'hourly_rate' => $hourlyRate,
                    'minimum_minutes' => $minimumMinutes,
                    'rounding_increment_minutes' => $roundingIncrement,
                    'window_start_time' => $windowStart,
                    'window_end_time' => $windowEnd,
                    'priority' => $priority,
                    'is_active' => true,
                ],
            );
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\PayPolicy;
use App\Models\Shift;
use App\Models\WorkSchedule;
use Illuminate\Database\Seeder;

class HrWorkSetupSeeder extends Seeder
{
    public function run(): void
    {
        PayPolicy::firstOrCreate(['code' => 'STANDARD', 'version' => 1], ['name' => 'Standard monthly pay', 'notes' => 'Demo policy: monthly entitlement, 30-day / 8-hour overtime basis. Review terms before company use.']);
        $shift = Shift::firstOrCreate(['name' => 'Day shift (08:00-17:00)'], ['start_time' => '08:00:00', 'end_time' => '17:00:00', 'break_start' => '12:00:00', 'break_end' => '13:00:00', 'grace_minutes' => 5]);
        $night = Shift::firstOrCreate(['name' => 'Night shift (22:00-06:00)'], ['start_time' => '22:00:00', 'end_time' => '06:00:00', 'break_paid' => true, 'grace_minutes' => 5]);
        $saturday = Shift::firstOrCreate(['name' => 'Saturday (08:00-12:00)'], ['start_time' => '08:00:00', 'end_time' => '12:00:00', 'grace_minutes' => 5]);
        $schedule = WorkSchedule::firstOrCreate(['name' => 'Standard Monday-Saturday']);
        if (! $schedule->days()->exists()) {
            foreach (range(0, 6) as $weekday) {
                $schedule->days()->create(['day_of_week' => $weekday, 'shift_id' => match ($weekday) {
                    0 => null, 6 => $saturday->id, default => $shift->id
                }]);
            }
        }
        $nightSchedule = WorkSchedule::firstOrCreate(['name' => 'Night Monday-Saturday']);
        if (! $nightSchedule->days()->exists()) {
            foreach (range(0, 6) as $weekday) {
                $nightSchedule->days()->create(['day_of_week' => $weekday, 'shift_id' => $weekday === 0 ? null : $night->id]);
            }
        }
    }
}

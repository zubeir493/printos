<?php

namespace Database\Factories;

use App\Models\Shift;
use App\Models\WorkSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkSchedule>
 */
class WorkScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Six-day week',
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (WorkSchedule $schedule): void {
            $shift = Shift::factory()->create();
            foreach (range(0, 6) as $weekday) {
                $schedule->days()->create(['day_of_week' => $weekday, 'shift_id' => $weekday === 0 ? null : $shift->id]);
            }
        });
    }
}

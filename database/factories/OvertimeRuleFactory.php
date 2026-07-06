<?php

namespace Database\Factories;

use App\Models\OvertimeRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OvertimeRule>
 */
class OvertimeRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'code' => $this->faker->unique()->slug(2),
            'minutes_basis' => $this->faker->randomElement(array_keys(OvertimeRule::minutesBasisOptions())),
            'applies_on_days' => [OvertimeRule::DAY_REGULAR],
            'multiplier' => $this->faker->randomFloat(2, 1, 2),
            'hourly_rate' => null,
            'minimum_minutes' => 0,
            'rounding_increment_minutes' => 1,
            'priority' => $this->faker->numberBetween(10, 100),
            'is_active' => true,
        ];
    }
}

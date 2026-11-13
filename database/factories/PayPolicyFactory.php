<?php

namespace Database\Factories;

use App\Models\PayPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayPolicy>
 */
class PayPolicyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Standard monthly', 'code' => fake()->unique()->slug(2), 'version' => 1,
        ];
    }
}

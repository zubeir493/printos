<?php

namespace Database\Factories;

use App\Models\JobOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class JobOrderTaskFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'job_order_id' => JobOrder::factory(),
            'name' => fake()->name(),
            'quantity' => fake()->numberBetween(-10000, 10000),
            'task_cost' => fake()->randomFloat(2, 0, 9999999999.99),
        ];
    }
}

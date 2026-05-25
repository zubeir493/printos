<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AccountFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'code' => fake()->word(),
            'type' => fake()->randomElement(['Asset', 'Liability', 'Equity', 'Revenue', 'Expense']),
        ];
    }
}

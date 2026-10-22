<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class WarehouseFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'code' => 'WH-'.fake()->unique()->numerify('########'),
            'location' => fake()->word(),
        ];
    }
}

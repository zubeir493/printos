<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryItemFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'sku' => fake()->unique()->bothify('SKU-########'),
            'unit' => fake()->word(),
            'average_cost' => fake()->randomFloat(2, 0, 9999999999999.99),
        ];
    }
}

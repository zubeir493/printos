<?php

namespace Database\Factories;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockTransferFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'transfer_number' => 'ST-TEST-'.fake()->unique()->numerify('########'),
            'from_warehouse_id' => Warehouse::factory(),
            'to_warehouse_id' => Warehouse::factory(),
            'transfer_date' => fake()->date(),
            'status' => fake()->randomElement(['draft', 'completed', 'cancelled']),
        ];
    }
}

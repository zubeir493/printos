<?php

namespace Database\Factories;

use App\Models\FromWarehouse;
use App\Models\ToWarehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockTransferFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'transfer_number' => fake()->word(),
            'from_warehouse_id' => FromWarehouse::factory(),
            'to_warehouse_id' => ToWarehouse::factory(),
            'transfer_date' => fake()->date(),
            'status' => fake()->randomElement(['draft', 'completed', 'cancelled']),
        ];
    }
}

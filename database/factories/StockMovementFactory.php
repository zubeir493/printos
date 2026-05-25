<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockMovementFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'inventory_item_id' => InventoryItem::factory(),
            'warehouse_id' => Warehouse::factory(),
            'type' => fake()->randomElement(['purchase', 'consumption', 'transfer_in', 'transfer_out', 'adjustment']),
            'reference_type' => fake()->word(),
            'reference_id' => fake()->randomNumber(),
            'quantity' => fake()->randomFloat(2, 0, 9999999999999.99),
            'unit_cost' => fake()->randomFloat(2, 0, 9999999999999.99),
            'total_cost' => fake()->randomFloat(2, 0, 9999999999999.99),
            'movement_date' => fake()->date(),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'po_number' => fake()->word(),
            'partner_id' => Partner::factory(),
            'order_date' => fake()->date(),
            'status' => fake()->randomElement(['draft', 'approved', 'received', 'cancelled']),
        ];
    }
}

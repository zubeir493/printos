<?php

namespace Database\Factories;

use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

class JobOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'job_order_number' => 'JO-'.str_pad(fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'cost_calc_file' => 'job-order-cost-calculations/costing.csv',
            'partner_id' => Partner::factory(),
            'job_type' => fake()->randomElement(['books', 'packages', 'labels', 'vouchers']),
            'production_mode' => fake()->randomElement(['make_to_order', 'make_to_stock']),
            'services' => [],
            'submission_date' => fake()->date(),
            'remarks' => fake()->text(),
            'subtotal' => fake()->randomFloat(2, 0, 9999.99),
            'tax_amount' => fake()->randomFloat(2, 0, 999.99),
            'total' => fake()->randomFloat(2, 0, 9999.99),
            'status' => fake()->randomElement(['draft', 'active', 'completed', 'cancelled']),
        ];
    }
}

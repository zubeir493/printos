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
            'name' => fake()->words(3, true), 'quantity' => 100, 'paper' => [], 'deliverables' => [],
            'job_order_number' => 'JO-'.str_pad(fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'cost_calc_file' => 'job-order-cost-calculations/costing.csv',
            'partner_id' => Partner::factory(),
            'job_type' => fake()->randomElement(['books', 'packages', 'labels', 'vouchers']),
            'production_mode' => 'make_to_order',
            'services' => [],
            'submission_date' => fake()->date(),
            'remarks' => fake()->text(),
            'subtotal' => 100,
            'tax_amount' => 0,
            'total' => 100,
            'status' => 'draft',
        ];
    }
}

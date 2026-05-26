<?php

namespace Database\Factories;

use App\Models\CostEstimate;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CostEstimate>
 */
class CostEstimateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'estimate_number' => 'EST-'.now()->format('Y').'-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'job_type' => fake()->randomElement(['books', 'packages', 'labels', 'vouchers']),
            'partner_id' => Partner::factory(),
            'services' => [],
            'remarks' => fake()->sentence(),
            'subtotal' => 1000,
            'tax_amount' => 150,
            'total' => 1150,
            'status' => 'draft',
        ];
    }
}

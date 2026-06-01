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
    protected $model = CostEstimate::class;

    public function definition(): array
    {
        return [
            'estimate_number' => 'CE-'.now()->format('Y').'-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'job_type' => 'labels',
            'partner_id' => Partner::factory(),
            'description' => fake()->words(3, true),
            'quantity' => 5000,
            'services' => [],
            'subtotal' => 0,
            'tax_amount' => 0,
            'total' => 0,
            'status' => 'draft',
        ];
    }
}

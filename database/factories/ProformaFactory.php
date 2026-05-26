<?php

namespace Database\Factories;

use App\Models\CostEstimate;
use App\Models\Partner;
use App\Models\Proforma;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Proforma>
 */
class ProformaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'proforma_number' => 'PF-'.now()->format('Y').'-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'cost_estimate_id' => CostEstimate::factory(),
            'partner_id' => Partner::factory(),
            'job_type' => fake()->randomElement(['books', 'packages', 'labels', 'vouchers']),
            'services' => [],
            'issue_date' => now(),
            'expiry_date' => now()->addDays(15),
            'remarks' => fake()->sentence(),
            'subtotal' => 1000,
            'tax_amount' => 150,
            'total' => 1150,
            'status' => 'draft',
        ];
    }
}

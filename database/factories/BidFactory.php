<?php

namespace Database\Factories;

use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

class BidFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'tender_reference' => fake()->bothify('TDR-####'),
            'partner_id' => Partner::factory()->customer(),
            'status' => 'draft',
            'submission_date' => now()->toDateString(),
            'deadline_date' => now()->addDays(14)->toDateString(),
            'estimated_value' => fake()->randomFloat(2, 10000, 500000),
            'bid_bond_amount' => null,
            'bid_files' => [],
            'notes' => fake()->sentence(),
        ];
    }
}

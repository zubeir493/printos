<?php

namespace Database\Factories;

use App\Models\Bid;
use App\Models\Bond;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

class BondFactory extends Factory
{
    protected $model = Bond::class;

    public function definition(): array
    {
        return [
            'type' => Bond::TYPE_BID,
            'bid_id' => Bid::factory(),
            'issuing_partner_id' => Partner::factory()->customer(),
            'amount' => fake()->randomFloat(2, 1000, 50000),
            'expiry_date' => now()->addDays(60)->toDateString(),
            'status' => Bond::STATUS_PENDING,
            'reference' => fake()->bothify('BND-####'),
        ];
    }

    public function bid(): static
    {
        return $this->state(fn (): array => [
            'type' => Bond::TYPE_BID,
        ]);
    }

    public function performance(): static
    {
        return $this->state(fn (): array => [
            'type' => Bond::TYPE_PERFORMANCE,
        ]);
    }
}

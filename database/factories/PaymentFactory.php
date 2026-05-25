<?php

namespace Database\Factories;

use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'payment_number' => fake()->word(),
            'partner_id' => Partner::factory(),
            'payment_date' => fake()->date(),
            'amount' => fake()->randomFloat(2, 0, 9999999999999.99),
            'direction' => fake()->randomElement(['inbound', 'outbound']),
            'method' => fake()->randomElement(['cash', 'bank', 'cheque']),
            'reference' => fake()->word(),
        ];
    }
}

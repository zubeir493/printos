<?php

namespace Database\Factories;

use App\Models\Bank;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Bank> */
class BankFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Bank', 'code' => fake()->unique()->bothify('BANK-########'),
            'account_number' => fake()->unique()->numerify('############'), 'account_holder_name' => 'Pilot Company',
            'bank_name' => fake()->company(), 'branch' => 'Main', 'current_balance' => 10000, 'status' => 'active',
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\AccountingIntegration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountingIntegration>
 */
class AccountingIntegrationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => fake()->unique()->slug(),
            'name' => fake()->company(),
            'enabled' => true,
            'timezone' => 'Africa/Addis_Ababa',
            'daily_cutoff' => '23:55',
        ];
    }
}

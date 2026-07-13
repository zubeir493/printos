<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\AccountingAccountMapping;
use App\Models\AccountingIntegration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountingAccountMapping>
 */
class AccountingAccountMappingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'accounting_integration_id' => AccountingIntegration::factory(),
            'account_id' => Account::factory(),
            'external_account_id' => fake()->unique()->bothify('####-###'),
        ];
    }
}

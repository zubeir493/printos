<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Bank;
use App\Models\CashDeposit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CashDepositFactory extends Factory
{
    protected $model = CashDeposit::class;

    public function definition(): array
    {
        return [
            'bank_id' => fn (): int => Bank::query()->first()?->id ?? Bank::create([
                'name' => 'Operating Bank',
                'code' => fake()->unique()->bothify('BANK-####'),
                'account_number' => fake()->unique()->numerify('##########'),
                'account_holder_name' => 'Printerp',
                'bank_name' => 'Operating Bank',
                'current_balance' => 0,
                'status' => 'active',
            ])->id,
            'cash_account_id' => fn (): int => Account::getSystemAccount(
                Account::CODE_CASH,
                'Cash in Hand',
                'Asset',
            )->id,
            'amount' => fake()->randomFloat(2, 100, 10000),
            'deposit_date' => fake()->date(),
            'reference' => fake()->bothify('SLIP-####'),
            'notes' => fake()->optional()->sentence(),
            'status' => CashDeposit::STATUS_PENDING,
            'created_by' => User::factory(),
        ];
    }
}

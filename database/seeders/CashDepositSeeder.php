<?php

namespace Database\Seeders;

use App\Models\Bank;
use App\Models\CashDeposit;
use Illuminate\Database\Seeder;

class CashDepositSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $bank = Bank::query()->where('status', 'active')->first();

        if ($bank) {
            CashDeposit::factory()->for($bank)->create();
        }
    }
}

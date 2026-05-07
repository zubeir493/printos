<?php

namespace Database\Seeders;

use App\Models\Bank;
use Illuminate\Database\Seeder;

class BankSeeder extends Seeder
{
    public function run(): void
    {
        $banks = [
            [
                'name' => 'BOA Zubeyr',
                'code' => 'BOAZ',
                'account_number' => '1000000001',
                'account_holder_name' => 'Zubeyr Abdella',
                'bank_name' => 'Bank of Abyssinia',
                'branch' => 'Ras Desta',
                'current_balance' => 150000.00,
                'status' => 'active',
                'notes' => 'Primary operating bank account.',
            ],
            [
                'name' => 'CBE Zubeyr',
                'code' => 'CBEZ',
                'account_number' => '1000000002',
                'account_holder_name' => 'Zubeyr Abdella',
                'bank_name' => 'CBE',
                'branch' => 'Ras Desta',
                'current_balance' => 50000.00,
                'status' => 'active',
                'notes' => 'Used for employee payroll disbursements.',
            ],
        ];

        foreach ($banks as $bank) {
            Bank::updateOrCreate(
                ['code' => $bank['code']],
                $bank
            );
        }
    }
}

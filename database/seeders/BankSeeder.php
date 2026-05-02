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
                'name' => 'Payroll Account',
                'code' => 'PAY',
                'account_number' => '1000000002',
                'account_holder_name' => 'PrintOS',
                'bank_name' => 'National Bank',
                'branch' => 'Payroll Branch',
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

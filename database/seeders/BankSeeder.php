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
                'name' => 'BOA',
                'code' => 'BOAN',
                'account_number' => '1000000001',
                'account_holder_name' => 'Nejashi Printing Press',
                'bank_name' => 'Bank of Abyssinia',
                'branch' => 'Ras Desta',
                'current_balance' => 0,
                'status' => 'active',
                'notes' => 'Primary operating bank account.',
            ],
            [
                'name' => 'CBE',
                'code' => 'CBEN',
                'account_number' => '1000000002',
                'account_holder_name' => 'Nejashi Printing Press',
                'bank_name' => 'CBE',
                'branch' => 'Ras Desta',
                'current_balance' => 0,
                'status' => 'active',
                'notes' => 'Used for employee payroll disbursements.',
            ],
        ];

        foreach ($banks as $bank) {
            Bank::updateOrCreate(
                ['account_number' => $bank['account_number']],
                $bank
            );
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\PayrollTaxRule;
use Illuminate\Database\Seeder;

class PayrollTaxRuleSeeder extends Seeder
{
    public function run(): void
    {
        $rule = PayrollTaxRule::updateOrCreate(
            [
                'name' => 'Ethiopia PAYE',
                'effective_from' => '2016-07-08',
            ],
            [
                'jurisdiction' => 'ET',
                'effective_until' => null,
                'is_active' => true,
            ],
        );

        $rule->brackets()->delete();

        foreach ([
            [0, 600, 0, 0],
            [601, 1650, 10, 60],
            [1651, 3200, 15, 142.50],
            [3201, 5250, 20, 302.50],
            [5251, 7800, 25, 565],
            [7801, 10900, 30, 955],
            [10901, null, 35, 1500],
        ] as [$min, $max, $rate, $deduction]) {
            $rule->brackets()->create([
                'min_income' => $min,
                'max_income' => $max,
                'rate' => $rate,
                'deduction' => $deduction,
            ]);
        }
    }
}

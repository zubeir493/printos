<?php

namespace Database\Factories;

use App\Models\PayrollRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollRun>
 */
class PayrollRunFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->monthName().' payroll',
            'period_type' => 'custom',
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'pay_date' => '2026-05-31',
            'status' => 'draft',
        ];
    }
}

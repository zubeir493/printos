<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\OvertimeRule;
use App\Models\PayrollOvertimeEntry;
use App\Models\PayrollRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollOvertimeEntry>
 */
class PayrollOvertimeEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $minutes = $this->faker->numberBetween(30, 240);
        $hourlyRate = $this->faker->randomFloat(2, 25, 100);
        $multiplier = $this->faker->randomFloat(2, 1, 2);

        return [
            'payroll_run_id' => PayrollRun::factory(),
            'employee_id' => Employee::factory(),
            'overtime_rule_id' => OvertimeRule::factory(),
            'date' => $this->faker->date(),
            'source_key' => $this->faker->unique()->uuid(),
            'status' => PayrollOvertimeEntry::STATUS_PENDING,
            'minutes' => $minutes,
            'hours' => round($minutes / 60, 2),
            'hourly_rate' => $hourlyRate,
            'multiplier' => $multiplier,
            'amount' => round(($minutes / 60) * $hourlyRate * $multiplier, 2),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeShiftOverride;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeShiftOverride>
 */
class EmployeeShiftOverrideFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(), 'date' => '2026-11-02', 'shift_id' => null, 'reason' => 'Approved rest day',
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeWorkAssignment;
use App\Models\PayPolicy;
use App\Models\WorkSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeWorkAssignment>
 */
class EmployeeWorkAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(), 'work_schedule_id' => WorkSchedule::factory(), 'pay_policy_id' => PayPolicy::factory(), 'effective_from' => '2026-01-01', 'reason' => 'Initial assignment',
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_id' => $this->faker->unique()->bothify('EMP-####'),
            'attendance_device_id' => $this->faker->unique()->bothify('DEV-####'),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'phone' => $this->faker->unique()->numerify('091#######'),
            'hire_date' => '2026-01-01',
            'status' => 'active',
            'employment_type' => 'permanent',
            'basic_salary' => 12000,
            'transport_allowance' => 0,
            'pension_enabled' => true,
        ];
    }
}

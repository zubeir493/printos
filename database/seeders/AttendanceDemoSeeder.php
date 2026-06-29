<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AttendanceDemoSeeder extends Seeder
{
    public function run(): void
    {
        $now = CarbonImmutable::parse('2026-06-24 09:00:00');

        foreach ($this->employees() as $employee) {
            DB::table('employees')->updateOrInsert(
                ['employee_id' => $employee['employee_id']],
                [
                    ...$employee,
                    'hire_date' => '2025-10-01',
                    'status' => 'active',
                    'employment_type' => 'permanent',
                    'department' => 'Production',
                    'position' => 'Operator',
                    'pension_enabled' => true,
                    'basic_salary' => 28000,
                    'transport_allowance' => 2500,
                    'overtime_multiplier' => 1.5,
                    'payment_method' => 'bank',
                    'bank_name' => 'Bank of Abyssinia',
                    'account_number' => '9000'.$employee['attendance_device_id'],
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function employees(): array
    {
        return [
            [
                'employee_id' => 'I-1',
                'attendance_device_id' => '22',
                'first_name' => 'Abas',
                'last_name' => 'Seman Mohammed',
            ],
            [
                'employee_id' => 'I-121',
                'attendance_device_id' => '96',
                'first_name' => 'Abrar',
                'last_name' => 'Kiyar Kemal',
            ],
            [
                'employee_id' => 'I-18',
                'attendance_device_id' => '3',
                'first_name' => 'Abdulkerim',
                'last_name' => 'Abdulmelik Amdega',
            ],
            [
                'employee_id' => 'I-32',
                'attendance_device_id' => '9',
                'first_name' => 'Abdulhalim',
                'last_name' => 'Hassen Abubeker',
            ],
        ];
    }
}

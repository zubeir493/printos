<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\PayPolicy;
use App\Models\WorkSchedule;
use Illuminate\Database\Seeder;

class AttendanceDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(HrWorkSetupSeeder::class);
        $policy = PayPolicy::where('code', 'STANDARD')->where('version', 1)->firstOrFail();
        $schedule = WorkSchedule::where('name', 'Standard Monday-Saturday')->firstOrFail();

        foreach ($this->employees() as $employee) {
            $record = Employee::updateOrCreate(
                ['attendance_device_id' => $employee['attendance_device_id']],
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
                    'payment_method' => 'bank',
                    'bank_name' => 'Bank of Abyssinia',
                    'account_number' => '9000'.$employee['attendance_device_id'],
                ],
            );
            if (! $record->salaryHistories()->exists()) {
                $record->salaryHistories()->create(['effective_date' => $record->hire_date, 'basic_salary' => $record->basic_salary, 'transport_allowance' => $record->transport_allowance, 'pension_enabled' => $record->pension_enabled, 'change_reason' => 'Initial demo salary terms']);
            }
            if (! $record->workAssignments()->exists()) {
                $record->workAssignments()->create(['work_schedule_id' => $schedule->id, 'pay_policy_id' => $policy->id, 'effective_from' => $record->hire_date, 'reason' => 'Demo work assignment']);
            }
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

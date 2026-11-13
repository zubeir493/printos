<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use Illuminate\Database\Seeder;

class LeaveTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (['Annual leave', 'Sick leave', 'Maternity leave', 'Paternity leave', 'Bereavement leave', 'Marriage leave', 'Unpaid leave'] as $name) {
            LeaveType::firstOrCreate(['name' => $name], ['is_paid' => $name !== 'Unpaid leave', 'annual_allowance_days' => null]);
        }
    }
}

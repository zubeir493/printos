<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\EmployeeScheduleAssignment;
use App\Models\WorkSchedule;

class ResolveEmployeeSchedule
{
    public function handle(Employee $employee, string $date): ?WorkSchedule
    {
        $assignment = EmployeeScheduleAssignment::query()
            ->where('employee_id', $employee->id)
            ->activeOn($date)
            ->with('workSchedule.days.shift')
            ->latest('effective_from')
            ->first();

        if ($assignment) {
            return $assignment->workSchedule;
        }

        return WorkSchedule::query()
            ->where('is_default', true)
            ->with('days.shift')
            ->first();
    }
}

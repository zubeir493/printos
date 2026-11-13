<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\EmployeeWorkAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class EndEmployeeWorkAssignment
{
    public function __construct(private UpdateEmployeeWorkAssignment $update) {}

    public function handle(EmployeeWorkAssignment $assignment): EmployeeWorkAssignment
    {
        Gate::authorize('update', $assignment->employee);

        return DB::transaction(function () use ($assignment): EmployeeWorkAssignment {
            Employee::whereKey($assignment->employee_id)->lockForUpdate()->firstOrFail();
            $assignment = EmployeeWorkAssignment::whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            if ($assignment->effective_to?->isSameDay(today())) {
                return $assignment;
            }
            if ($assignment->effective_from->gt(today()) || ($assignment->effective_to && $assignment->effective_to->lt(today()))) {
                throw ValidationException::withMessages(['effective_to' => 'Only a current assignment can be ended today. Edit future or ended assignments separately.']);
            }

            return $this->update->handle($assignment, ['effective_to' => today()->toDateString(), 'reason' => $assignment->reason."\nEnded on ".today()->toDateString().' by user '.auth()->id().'.']);
        }, 3);
    }
}

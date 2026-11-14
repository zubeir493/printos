<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\EmployeeWorkAssignment;
use App\Models\PayPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AssignEmployeeWork
{
    public function handleCompanyRules(Employee $employee, int $scheduleId, string $from, ?string $reason = null, ?string $to = null): EmployeeWorkAssignment
    {
        Gate::authorize('update', $employee);

        return DB::transaction(function () use ($employee, $scheduleId, $from, $reason, $to): EmployeeWorkAssignment {
            $policy = PayPolicy::forDate($from);
            if (! $policy) {
                throw ValidationException::withMessages(['effective_from' => 'Configure payroll rules in Settings first.']);
            }
            $assignment = $this->handle($employee, $scheduleId, $policy->id, $from, $reason, $to);
            $changes = PayPolicy::where('is_active', true)->whereDate('effective_from', '>', $from)
                ->when($to, fn ($query) => $query->whereDate('effective_from', '<=', $to))
                ->orderBy('effective_from')->orderBy('id')->get()->groupBy('effective_from')->map->last();
            foreach ($changes as $date => $change) {
                $this->handle($employee, $scheduleId, $change->id, $date, $reason, $to);
            }

            return $assignment->fresh();
        });
    }

    public function handle(Employee $employee, int $scheduleId, int $policyId, string $from, ?string $reason = null, ?string $to = null): EmployeeWorkAssignment
    {
        Gate::authorize('update', $employee);

        return DB::transaction(function () use ($employee, $scheduleId, $policyId, $from, $reason, $to): EmployeeWorkAssignment {
            Employee::query()->lockForUpdate()->findOrFail($employee->id);
            $from = CarbonImmutable::parse($from)->toDateString();
            $current = $employee->workAssignments()->whereDate('effective_from', '<', $from)->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $from))->first();
            if ($current) {
                $current->update(['effective_to' => CarbonImmutable::parse($from)->subDay()->toDateString()]);
            }

            return $employee->workAssignments()->create(['work_schedule_id' => $scheduleId, 'pay_policy_id' => $policyId, 'effective_from' => $from, 'effective_to' => $to, 'reason' => $reason]);
        });
    }
}

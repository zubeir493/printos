<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\EmployeeWorkAssignment;
use App\Models\PayPolicy;
use App\Models\PayrollRun;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UpdateEmployeeWorkAssignment
{
    /** @param array<string, mixed> $data */
    public function handle(EmployeeWorkAssignment $assignment, array $data): EmployeeWorkAssignment
    {
        Gate::authorize('update', $assignment->employee);

        return $this->persist($assignment, $data);
    }

    public function revisePayPolicy(EmployeeWorkAssignment $assignment, PayPolicy $policy, string $from): void
    {
        Gate::authorize('create', PayPolicy::class);
        DB::transaction(function () use ($assignment, $policy, $from): void {
            if ($assignment->effective_from->toDateString() >= $from) {
                $this->persist($assignment, ['pay_policy_id' => $policy->id]);

                return;
            }
            $to = $assignment->effective_to?->toDateString();
            $this->persist($assignment, ['effective_to' => CarbonImmutable::parse($from)->subDay()->toDateString()]);
            $assignment->employee->workAssignments()->create([
                'work_schedule_id' => $assignment->work_schedule_id, 'pay_policy_id' => $policy->id,
                'effective_from' => $from, 'effective_to' => $to, 'reason' => 'Company pay rules updated',
            ]);
        });
    }

    /** @param array<string, mixed> $data */
    private function persist(EmployeeWorkAssignment $assignment, array $data): EmployeeWorkAssignment
    {

        return DB::transaction(function () use ($assignment, $data): EmployeeWorkAssignment {
            Employee::whereKey($assignment->employee_id)->lockForUpdate()->firstOrFail();
            $assignment = EmployeeWorkAssignment::whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            $originalFrom = $assignment->effective_from;
            $originalTo = $assignment->effective_to;
            $assignment->fill(collect($data)->only(['work_schedule_id', 'pay_policy_id', 'effective_from', 'effective_to', 'reason'])->all());
            Validator::make($assignment->attributesToArray(), [
                'effective_from' => ['required', 'date'],
                'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
                'reason' => ['required', 'string'],
            ])->validate();
            $through = max($originalTo?->toDateString() ?? '9999-12-31', $assignment->effective_to?->toDateString() ?? '9999-12-31');
            $from = null;
            if ($assignment->isDirty(['work_schedule_id', 'pay_policy_id', 'effective_from'])) {
                $from = min($originalFrom->toDateString(), $assignment->effective_from->toDateString());
            } elseif ($assignment->isDirty('effective_to')) {
                $from = CarbonImmutable::parse(min($originalTo?->toDateString() ?? '9999-12-31', $assignment->effective_to?->toDateString() ?? '9999-12-31'))->addDay()->toDateString();
            }
            if ($from !== null && PayrollRun::whereIn('status', ['approved', 'paid'])->whereDate('period_start', '<=', $through)->whereDate('period_end', '>=', $from)->whereHas('employees', fn ($query) => $query->where('employee_id', $assignment->employee_id))->exists()) {
                throw ValidationException::withMessages(['effective_from' => 'This change would alter a posted payroll period. Create a new assignment starting after that period.']);
            }
            $assignment->save();
            if ($from !== null) {
                PayrollRun::where('status', 'draft')->whereDate('period_start', '<=', $through)->whereDate('period_end', '>=', $from)->whereHas('employees', fn ($query) => $query->where('employee_id', $assignment->employee_id))->update(['attendance_reviewed_at' => null, 'attendance_reviewed_by' => null, 'attendance_review_note' => null]);
            }

            return $assignment;
        }, 3);
    }
}

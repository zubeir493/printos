<?php

namespace App\Services\Hr;

use App\Models\EmployeeWorkAssignment;
use App\Models\PayPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SavePayrollPolicy
{
    /** @param array<string, mixed> $data */
    public function handle(array $data, string $effectiveFrom): PayPolicy
    {
        Gate::authorize('create', PayPolicy::class);
        Validator::make(['effective_from' => $effectiveFrom], ['effective_from' => ['required', 'date', 'after_or_equal:today']])->validate();

        return DB::transaction(function () use ($data, $effectiveFrom): PayPolicy {
            $policies = PayPolicy::orderBy('id')->lockForUpdate()->get();
            $current = $policies->where('is_active', true)->last();
            if ($current?->effective_from && $effectiveFrom < $current->effective_from) {
                throw ValidationException::withMessages(['effective_from' => 'Choose a date on or after the latest rule change: '.$current->effective_from.'.']);
            }
            $fields = ['monthly_day_divisor', 'standard_daily_minutes', 'deduct_lateness', 'prorate_allowance', 'day_overtime_multiplier', 'night_overtime_multiplier', 'rest_overtime_multiplier', 'holiday_overtime_multiplier', 'night_start', 'night_end', 'overtime_increment_minutes', 'maximum_daily_overtime_minutes', 'maximum_weekly_overtime_minutes'];
            $policy = $current?->replicate()->unsetRelations() ?? new PayPolicy(['name' => 'Company pay rules', 'code' => 'STANDARD']);
            $policy->fill(collect($data)->only($fields)->all());
            if ($current && collect($fields)->every(fn (string $field): bool => (string) $policy->getAttribute($field) === (string) $current->getAttribute($field))) {
                return $current;
            }
            $policy->version = (int) $policies->where('code', $policy->code)->max('version') + 1;
            $policy->is_active = true;
            $policy->effective_from = $effectiveFrom;
            $policy->save();
            $from = CarbonImmutable::parse($effectiveFrom)->toDateString();
            $assignments = EmployeeWorkAssignment::with('employee')->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $from))->orderBy('employee_id')->orderBy('effective_from')->lockForUpdate()->get();
            foreach ($assignments as $assignment) {
                app(UpdateEmployeeWorkAssignment::class)->revisePayPolicy($assignment, $policy, $from);
            }

            return $policy;
        }, 3);
    }
}

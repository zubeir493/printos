<?php

namespace App\Services\Hr;

use App\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ReviseEmployeeSalary
{
    /** @param array{transport_allowance?: float|int|string, pension_enabled?: bool|int|string} $terms */
    public function handle(Employee $employee, float $amount, string $effectiveDate, string $reason, array $terms = []): void
    {
        Gate::authorize('update', $employee);
        if (! is_finite($amount) || $amount < 0 || blank($reason)) {
            throw ValidationException::withMessages(['basic_salary' => 'Supply a nonnegative salary and a reason.']);
        }
        Validator::make($terms, ['transport_allowance' => ['sometimes', 'numeric', 'min:0'], 'pension_enabled' => ['sometimes', 'boolean']])->validate();
        DB::transaction(function () use ($employee, $amount, $effectiveDate, $reason, $terms): void {
            $employee = Employee::query()->lockForUpdate()->findOrFail($employee->id);
            if (CarbonImmutable::parse($effectiveDate)->lt($employee->hire_date)) {
                throw ValidationException::withMessages(['effective_date' => 'A revision cannot precede employment.']);
            }
            $employee->salaryHistories()->create(['basic_salary' => $amount, 'transport_allowance' => $terms['transport_allowance'] ?? $employee->transport_allowance, 'pension_enabled' => $terms['pension_enabled'] ?? $employee->pension_enabled, 'effective_date' => $effectiveDate, 'change_reason' => $reason.' (user '.auth()->id().')']);
            if (CarbonImmutable::parse($effectiveDate)->lte(today())) {
                $current = $employee->salaryHistories()->whereDate('effective_date', '<=', today())->latest('effective_date')->latest('id')->firstOrFail();
                $employee->updateQuietly(['basic_salary' => $current->basic_salary, 'transport_allowance' => $current->transport_allowance, 'pension_enabled' => $current->pension_enabled]);
            }
        }, 3);
    }
}

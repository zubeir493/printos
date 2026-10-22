<?php

namespace App\Services\Hr;

use App\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ReviseEmployeeSalary
{
    public function handle(Employee $employee, float $amount, string $effectiveDate, string $reason): void
    {
        Gate::authorize('update', $employee);
        if (! is_finite($amount) || $amount < 0 || blank($reason)) {
            throw ValidationException::withMessages(['basic_salary' => 'Supply a nonnegative salary and a reason.']);
        }
        DB::transaction(function () use ($employee, $amount, $effectiveDate, $reason): void {
            $employee = Employee::query()->lockForUpdate()->findOrFail($employee->id);
            if (CarbonImmutable::parse($effectiveDate)->lt($employee->hire_date)) {
                throw ValidationException::withMessages(['effective_date' => 'A revision cannot precede employment.']);
            }
            $employee->salaryHistories()->create(['basic_salary' => $amount, 'effective_date' => $effectiveDate, 'change_reason' => $reason.' (user '.auth()->id().')']);
            if (CarbonImmutable::parse($effectiveDate)->lte(today())) {
                $employee->updateQuietly(['basic_salary' => $employee->salaryHistories()->whereDate('effective_date', '<=', today())->latest('effective_date')->latest('id')->value('basic_salary')]);
            }
        }, 3);
    }
}

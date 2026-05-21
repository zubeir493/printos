<?php

namespace App\Observers;

use App\Models\Employee;
use Illuminate\Support\Facades\Storage;

class EmployeeObserver
{
    /**
     * Handle the Employee "created" event.
     */
    public function created(Employee $employee): void
    {
        $this->recordSalaryHistory($employee, 'Initial rate');
    }

    /**
     * Handle the Employee "updated" event.
     */
    public function updated(Employee $employee): void
    {
        if ($employee->wasChanged(['basic_salary', 'overtime_multiplier'])) {
            $this->recordSalaryHistory($employee, 'Rate update');
        }

        // Cleanup old image from S3 if it was changed
        if ($employee->wasChanged('image') && $employee->getOriginal('image')) {
            Storage::disk('s3')->delete($employee->getOriginal('image'));
        }
    }

    protected function recordSalaryHistory(Employee $employee, string $reason): void
    {
        $employee->salaryHistories()->create([
            'basic_salary' => $employee->basic_salary ?? 0,
            'overtime_multiplier' => $employee->overtime_multiplier ?? 1,
            'effective_date' => now(),
            'change_reason' => $reason,
        ]);
    }

    /**
     * Handle the Employee "deleted" event.
     */
    public function deleted(Employee $employee): void
    {
        if (! $employee->isForceDeleting()) {
            return;
        }

        if ($employee->image) {
            Storage::disk('s3')->delete($employee->image);
        }
    }

    /**
     * Handle the Employee "restored" event.
     */
    public function restored(Employee $employee): void
    {
        //
    }

    /**
     * Handle the Employee "force deleted" event.
     */
    public function forceDeleted(Employee $employee): void
    {
        //
    }
}

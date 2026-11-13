<?php

namespace App\Services\Hr;

use App\Models\PayrollOvertimeEntry;
use App\Models\PayrollRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ReviewOvertime
{
    public function handle(PayrollOvertimeEntry $entry, bool $approve, string $reason): void
    {
        Gate::authorize('update', $entry);
        DB::transaction(function () use ($entry, $approve, $reason): void {
            $entry = PayrollOvertimeEntry::with('payrollRun')->lockForUpdate()->findOrFail($entry->id);
            if (blank($reason) || ! $entry->pay_policy_id || ($approve && $entry->minutes <= 0) || ($entry->payroll_run_id && $entry->payrollRun?->status !== 'draft')) {
                throw ValidationException::withMessages(['reason' => 'A reason is required and finalized overtime cannot be changed.']);
            }
            $entry->update(['status' => $approve ? 'approved' : 'rejected', 'notes' => $reason, 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);
            PayrollRun::where('status', 'draft')->whereDate('period_start', '<=', $entry->date)->whereDate('period_end', '>=', $entry->date)->update(['attendance_reviewed_at' => null, 'attendance_reviewed_by' => null]);
        });
    }
}

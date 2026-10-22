<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ReviewLeaveRequest
{
    public function handle(LeaveRequest $request, string $decision, string $reason): LeaveRequest
    {
        Gate::authorize('update', $request);

        return DB::transaction(function () use ($request, $decision, $reason): LeaveRequest {
            $request = LeaveRequest::query()->lockForUpdate()->findOrFail($request->id);
            Employee::query()->lockForUpdate()->findOrFail($request->employee_id);
            if ($request->status !== 'pending' || ! in_array($decision, ['approved', 'rejected'], true) || blank($reason)) {
                throw ValidationException::withMessages(['decision_reason' => 'Only pending requests can be decided. Supply a decision reason.']);
            }
            if ($decision === 'approved') {
                if (LeaveRequest::query()->where('employee_id', $request->employee_id)->where('status', 'approved')
                    ->whereDate('start_date', '<=', $request->end_date)->whereDate('end_date', '>=', $request->start_date)->exists()) {
                    throw ValidationException::withMessages(['start_date' => 'This request overlaps approved leave.']);
                }
                $year = $request->start_date->year;
                if ($request->end_date->year !== $year) {
                    throw ValidationException::withMessages(['end_date' => 'Split requests at the calendar year boundary.']);
                }
                $balance = $this->balance($request->employee_id, $request->leaveType, $year);
                $minutes = $request->minutes ?? (int) (($request->start_date->diffInDays($request->end_date) + 1) * 480);
                if ($balance !== null && $minutes > $balance) {
                    throw ValidationException::withMessages(['minutes' => 'The request exceeds the remaining annual leave balance.']);
                }
            }
            $request->update(['status' => $decision, 'decision_reason' => $reason, 'approved_by' => auth()->id(), 'approved_at' => now()]);

            return $request->refresh();
        }, 3);
    }

    public function balance(int $employeeId, LeaveType $type, int $year): ?int
    {
        if ($type->annual_allowance_days === null) {
            return null;
        }
        $used = LeaveRequest::query()->where('employee_id', $employeeId)->where('leave_type_id', $type->id)->where('status', 'approved')
            ->whereYear('start_date', $year)->get()->sum(fn (LeaveRequest $request): int => $request->minutes ?? (int) (($request->start_date->diffInDays($request->end_date) + 1) * 480));

        return max(0, (int) round((float) $type->annual_allowance_days * 480) - $used);
    }
}

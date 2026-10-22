<?php

namespace App\Models;

use App\Observers\LeaveRequestObserver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[ObservedBy(LeaveRequestObserver::class)]
class LeaveRequest extends Model
{
    use HasFactory;
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty()->useLogName('hr_payroll');
    }

    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'minutes',
        'status',
        'decision_reason',
        'reason',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $request): void {
            if ($request->start_time || $request->end_time) {
                if (! $request->start_time || ! $request->end_time || ! $request->start_date->isSameDay($request->end_date)) {
                    throw ValidationException::withMessages(['start_time' => 'Supply both times for a single-day request, or use total minutes for a multi-day request.']);
                }
                $start = CarbonImmutable::parse($request->start_time);
                $end = CarbonImmutable::parse($request->end_time);
                if ($end->lte($start)) {
                    throw ValidationException::withMessages(['end_time' => 'Leave end time must be after its start time.']);
                }
                $request->minutes = (int) $start->diffInMinutes($end);
            }
            if ($request->end_date->lt($request->start_date) || ($request->minutes !== null && ((int) $request->minutes <= 0 || (int) $request->minutes > ($request->start_date->diffInDays($request->end_date) + 1) * 480))) {
                throw ValidationException::withMessages(['end_date' => 'Select valid dates and positive leave minutes within the requested duration.']);
            }
        });
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }
}

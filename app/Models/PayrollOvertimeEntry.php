<?php

namespace App\Models;

use Database\Factories\PayrollOvertimeEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class PayrollOvertimeEntry extends Model
{
    /** @use HasFactory<PayrollOvertimeEntryFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'payroll_run_id',
        'payroll_run_employee_id',
        'employee_id',
        'pay_policy_id', 'category', 'started_at', 'ended_at', 'calculation_snapshot',
        'overtime_rule_id',
        'date',
        'source_type',
        'source_id',
        'source_key',
        'status',
        'minutes',
        'hours',
        'hourly_rate',
        'multiplier',
        'amount',
        'manual_amount',
        'notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected static function booted(): void
    {
        $guard = function (self $entry): void {
            $runId = $entry->getRawOriginal('payroll_run_id');
            if ($entry->exists && $runId && PayrollRun::whereKey($runId)->where('status', '!=', 'draft')->exists()) {
                throw ValidationException::withMessages(['status' => 'Finalized overtime cannot be changed.']);
            }
        };
        static::updating($guard);
        static::deleting($guard);
    }

    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime', 'ended_at' => 'immutable_datetime', 'calculation_snapshot' => 'array',
            'date' => 'date',
            'minutes' => 'integer',
            'hours' => 'decimal:2',
            'hourly_rate' => 'decimal:4',
            'multiplier' => 'decimal:4',
            'amount' => 'decimal:2',
            'manual_amount' => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function payrollRunEmployee(): BelongsTo
    {
        return $this->belongsTo(PayrollRunEmployee::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function overtimeRule(): BelongsTo
    {
        return $this->belongsTo(OvertimeRule::class);
    }

    public function payPolicy(): BelongsTo
    {
        return $this->belongsTo(PayPolicy::class);
    }

    public function getCategoryLabelAttribute(): string
    {
        return match ($this->category) {
            'day' => 'Daytime overtime', 'night' => 'Nighttime overtime', 'rest' => 'Rest-day work', 'holiday' => 'Public-holiday work', default => 'Overtime'
        };
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}

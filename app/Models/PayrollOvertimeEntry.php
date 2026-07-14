<?php

namespace App\Models;

use Database\Factories\PayrollOvertimeEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    protected function casts(): array
    {
        return [
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

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}

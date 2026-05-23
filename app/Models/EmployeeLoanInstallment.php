<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLoanInstallment extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_loan_id',
        'due_date',
        'amount',
        'paid_amount',
        'status',
        'payroll_run_employee_id',
        'payment_id',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(EmployeeLoan::class, 'employee_loan_id');
    }

    public function payrollRunEmployee(): BelongsTo
    {
        return $this->belongsTo(PayrollRunEmployee::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function scopeDueForPeriod(Builder $query, string $periodStart, string $periodEnd): Builder
    {
        return $query
            ->where('status', 'pending')
            ->whereDate('due_date', '>=', $periodStart)
            ->whereDate('due_date', '<=', $periodEnd);
    }
}

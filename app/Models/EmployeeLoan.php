<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLoan extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'loan_date',
        'return_date',
        'amount',
        'reason',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'loan_date' => 'date',
            'return_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function scopeDeductibleForPeriod(Builder $query, string $periodStart, string $periodEnd): Builder
    {
        return $query
            ->where('status', 'active')
            ->whereDate('return_date', '>=', $periodStart)
            ->whereDate('return_date', '<=', $periodEnd);
    }
}

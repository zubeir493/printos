<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRunEmployee extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_run_id',
        'employee_id',
        'basic_salary',
        'time_on_duty',
        'pay_per_hour',
        'bonus',
        'transport_allowance',
        'pension_11',
        'overtime_hours',
        'overtime_amount',
        'gross_earning',
        'taxable_amount',
        'income_tax',
        'penalty_hours',
        'penalty_amount',
        'pension_18',
        'loan',
        'workers_union',
        'total_deduction',
        'net_pay',
        'calculation_snapshot',
        'payment_id',
    ];

    protected function casts(): array
    {
        return [
            'calculation_snapshot' => 'array',
        ];
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(PayrollLineItem::class);
    }
}

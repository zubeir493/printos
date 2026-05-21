<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollTaxBracket extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_tax_rule_id',
        'min_income',
        'max_income',
        'rate',
        'deduction',
    ];

    protected function casts(): array
    {
        return [
            'min_income' => 'decimal:2',
            'max_income' => 'decimal:2',
            'rate' => 'decimal:2',
            'deduction' => 'decimal:2',
        ];
    }

    public function payrollTaxRule(): BelongsTo
    {
        return $this->belongsTo(PayrollTaxRule::class);
    }
}

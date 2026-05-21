<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollLineItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_run_employee_id',
        'type',
        'code',
        'description',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function payrollRunEmployee(): BelongsTo
    {
        return $this->belongsTo(PayrollRunEmployee::class);
    }
}

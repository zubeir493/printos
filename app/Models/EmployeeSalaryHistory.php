<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSalaryHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'basic_salary',
        'transport_allowance',
        'pension_enabled',
        'effective_date',
        'change_reason',
    ];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'basic_salary' => 'decimal:2',
            'transport_allowance' => 'decimal:2',
            'pension_enabled' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

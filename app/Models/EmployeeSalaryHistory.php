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
        'overtime_multiplier',
        'effective_date',
        'change_reason',
    ];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'basic_salary' => 'decimal:2',
            'overtime_multiplier' => 'decimal:4',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

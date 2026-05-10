<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'first_name',
        'last_name',
        'image',
        'phone',
        'hire_date',
        'status',
        'department',
        'position',
        'basic_salary',
        'hourly_overtime_rate',
        'holiday_overtime_rate',
        'payment_method',
        'bank_name',
        'account_number',
    ];

    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
            'basic_salary' => 'decimal:2',
            'hourly_overtime_rate' => 'decimal:2',
            'holiday_overtime_rate' => 'decimal:2',
        ];
    }

    public function salaryHistories(): HasMany
    {
        return $this->hasMany(EmployeeSalaryHistory::class);
    }

    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }
}

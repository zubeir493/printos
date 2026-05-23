<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Employee extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['employee_id', 'attendance_device_id', 'first_name', 'last_name', 'phone', 'hire_date', 'termination_date', 'status', 'employment_type', 'department', 'position', 'tax_id', 'pension_enabled', 'basic_salary', 'transport_allowance', 'overtime_multiplier', 'payment_method'])
            ->logOnlyDirty()
            ->useLogName('employee');
    }

    protected $fillable = [
        'employee_id',
        'attendance_device_id',
        'first_name',
        'last_name',
        'image',
        'phone',
        'hire_date',
        'termination_date',
        'status',
        'employment_type',
        'department',
        'position',
        'tax_id',
        'pension_enabled',
        'basic_salary',
        'transport_allowance',
        'overtime_multiplier',
        'payment_method',
        'bank_name',
        'account_number',
    ];

    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
            'termination_date' => 'date',
            'pension_enabled' => 'boolean',
            'basic_salary' => 'decimal:2',
            'transport_allowance' => 'decimal:2',
            'overtime_multiplier' => 'decimal:4',
        ];
    }

    public function salaryHistories(): HasMany
    {
        return $this->hasMany(EmployeeSalaryHistory::class);
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class);
    }

    public function attendanceDailySummaries(): HasMany
    {
        return $this->hasMany(AttendanceDailySummary::class);
    }

    public function attendancePeriodSummaries(): HasMany
    {
        return $this->hasMany(AttendancePeriodSummary::class);
    }

    public function attendanceSegments(): HasMany
    {
        return $this->hasMany(AttendanceSegment::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(EmployeeLoan::class);
    }

    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }
}

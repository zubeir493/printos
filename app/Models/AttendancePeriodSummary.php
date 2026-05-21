<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendancePeriodSummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_import_id',
        'employee_id',
        'period_start',
        'period_end',
        'shift_type',
        'work_days',
        'actual_days',
        'absent_days',
        'late_minutes',
        'early_minutes',
        'lunch_minutes',
        'overtime_minutes',
        'holiday_days',
        'leave_days',
        'dayoff_days',
        'work_time_hours',
        'work_percentage',
        'raw_data',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'raw_data' => 'array',
        ];
    }

    public function attendanceImport(): BelongsTo
    {
        return $this->belongsTo(AttendanceImport::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

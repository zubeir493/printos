<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceDailySummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'date',
        'expected_minutes',
        'worked_minutes',
        'regular_minutes',
        'late_minutes',
        'overtime_minutes',
        'holiday_minutes',
        'night_minutes',
        'absence_minutes',
        'paid_leave_minutes',
        'unpaid_leave_minutes',
        'status',
        'calculation_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'calculation_snapshot' => 'array',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

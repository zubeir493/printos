<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceSegment extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_import_id',
        'attendance_import_row_id',
        'employee_id',
        'date',
        'fp_no',
        'schedule_name',
        'scheduled_start',
        'scheduled_end',
        'clock_in',
        'clock_out',
        'late_minutes',
        'early_minutes',
        'worked_minutes',
        'overtime_minutes',
        'day_fraction',
        'status',
        'exception',
        'correction_reason',
        'raw_data',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'day_fraction' => 'decimal:2',
            'raw_data' => 'array',
        ];
    }

    public function attendanceImport(): BelongsTo
    {
        return $this->belongsTo(AttendanceImport::class);
    }

    public function attendanceImportRow(): BelongsTo
    {
        return $this->belongsTo(AttendanceImportRow::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

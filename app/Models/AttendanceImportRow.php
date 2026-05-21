<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceImportRow extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_import_id',
        'row_number',
        'raw_data',
        'status',
        'error_message',
        'attendance_log_id',
    ];

    protected function casts(): array
    {
        return [
            'raw_data' => 'array',
            'row_number' => 'integer',
        ];
    }

    public function attendanceImport(): BelongsTo
    {
        return $this->belongsTo(AttendanceImport::class);
    }

    public function attendanceLog(): BelongsTo
    {
        return $this->belongsTo(AttendanceLog::class);
    }

    public function attendanceSegment(): BelongsTo
    {
        return $this->belongsTo(AttendanceSegment::class);
    }
}

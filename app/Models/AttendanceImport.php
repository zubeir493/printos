<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceImport extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_name',
        'file_path',
        'report_type',
        'shift_type',
        'period_start',
        'period_end',
        'status',
        'total_rows',
        'successful_rows',
        'failed_rows',
        'created_by',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'total_rows' => 'integer',
            'successful_rows' => 'integer',
            'failed_rows' => 'integer',
            'period_start' => 'date',
            'period_end' => 'date',
            'processed_at' => 'datetime',
        ];
    }

    public function rows(): HasMany
    {
        return $this->hasMany(AttendanceImportRow::class);
    }

    public function periodSummaries(): HasMany
    {
        return $this->hasMany(AttendancePeriodSummary::class);
    }

    public function segments(): HasMany
    {
        return $this->hasMany(AttendanceSegment::class);
    }
}

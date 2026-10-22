<?php

namespace App\Models;

use App\Services\Hr\RebuildAttendanceDailySummaries;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class AttendanceSegment extends Model
{
    use HasFactory;

    public function save(array $options = []): bool
    {
        return DB::transaction(fn (): bool => parent::save($options));
    }

    protected static function booted(): void
    {
        static::updated(function (self $segment): void {
            $builder = app(RebuildAttendanceDailySummaries::class);
            $originalDate = CarbonImmutable::parse($segment->getRawOriginal('date'))->toDateString();
            $builder->forPeriod([(int) $segment->getRawOriginal('employee_id')], $originalDate, $originalDate);
            $builder->forPeriod([(int) $segment->employee_id], $segment->date->toDateString(), $segment->date->toDateString());
            PayrollRun::query()->where('status', 'draft')->whereDate('period_start', '<=', max($originalDate, $segment->date->toDateString()))->whereDate('period_end', '>=', min($originalDate, $segment->date->toDateString()))->update(['attendance_reviewed_at' => null, 'attendance_reviewed_by' => null]);
        });
    }

    protected $fillable = [
        'attendance_import_id',
        'attendance_import_row_id',
        'employee_id',
        'shift_id',
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

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PayrollRun extends Model
{
    use HasFactory;
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty()->useLogName('hr_payroll');
    }

    protected $fillable = [
        'approved_by', 'attendance_reviewed_at', 'attendance_reviewed_by', 'attendance_review_note',
        'name',
        'period_type',
        'payroll_month',
        'department',
        'employment_type',
        'selected_employee_ids',
        'excluded_employee_ids',
        'period_start',
        'period_end',
        'pay_date',
        'status',
        'created_by',
        'posted_at',
        'paid_at',
        'prepared_at',
        'journal_entry_id',
    ];

    protected $attributes = [
        'status' => 'draft',
        'period_type' => 'monthly',
    ];

    protected function casts(): array
    {
        return [
            'attendance_reviewed_at' => 'datetime',
            'payroll_month' => 'date',
            'period_start' => 'date',
            'period_end' => 'date',
            'pay_date' => 'date',
            'selected_employee_ids' => 'array',
            'excluded_employee_ids' => 'array',
            'posted_at' => 'datetime',
            'paid_at' => 'datetime',
            'prepared_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $run): void {
            if ($run->getOriginal('status') !== 'draft' && collect(array_keys($run->getDirty()))->intersect(['name', 'period_type', 'period_start', 'period_end', 'pay_date', 'payroll_month', 'department', 'employment_type', 'selected_employee_ids', 'excluded_employee_ids'])->isNotEmpty()) {
                throw new \RuntimeException('Approved payroll settings are locked.');
            }
        });
        static::deleting(function (self $run): void {
            if ($run->status !== 'draft') {
                throw new \RuntimeException('Posted payroll cannot be deleted.');
            }
        });
    }

    public function paymentBatches(): HasMany
    {
        return $this->hasMany(PayrollPaymentBatch::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(PayrollRunEmployee::class);
    }

    public function overtimeEntries(): HasMany
    {
        return $this->hasMany(PayrollOvertimeEntry::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * @return array<int, string>
     */
    public static function decodeEmploymentTypes(null|array|string $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value));
        }

        if (! $value) {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? array_values(array_filter($decoded)) : [$value];
    }

    /**
     * @param  array<int, string>|string|null  $value
     */
    public static function encodeEmploymentTypes(array|string|null $value): ?string
    {
        $types = static::decodeEmploymentTypes($value);

        return $types === [] ? null : json_encode($types);
    }
}

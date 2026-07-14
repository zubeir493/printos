<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    use HasFactory;

    protected $fillable = [
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

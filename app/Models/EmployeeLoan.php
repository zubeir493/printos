<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class EmployeeLoan extends Model
{
    use HasFactory;
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty()->useLogName('hr_payroll');
    }

    protected $fillable = [
        'approved_by', 'approved_at', 'disbursement_payment_id', 'legacy_disbursement_confirmed',
        'employee_id',
        'loan_date',
        'return_date',
        'amount',
        'installment_count',
        'reason',
        'status',
    ];

    protected $attributes = ['status' => 'pending', 'installment_count' => 1];

    public function isFunded(): bool
    {
        return $this->legacy_disbursement_confirmed || ($this->disbursement_payment_id && Payment::query()->whereKey($this->disbursement_payment_id)->whereNull('voided_at')->exists());
    }

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'legacy_disbursement_confirmed' => 'boolean',
            'loan_date' => 'date',
            'return_date' => 'date',
            'amount' => 'decimal:2',
            'installment_count' => 'integer',
        ];
    }

    public function disbursementPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'disbursement_payment_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(EmployeeLoanInstallment::class);
    }

    protected static function booted(): void
    {
        static::saving(function (self $loan): void {
            if ((float) $loan->amount <= 0 || (int) $loan->installment_count < 1 || (int) $loan->installment_count > 120) {
                throw ValidationException::withMessages(['amount' => 'A positive amount and 1 to 120 installments are required.']);
            }
        });
        static::created(function (self $loan): void {
            $loan->createInstallments();
        });
    }

    public function createInstallments(): void
    {
        if ($this->installments()->exists()) {
            return;
        }

        $count = max(1, (int) $this->installment_count);
        $baseAmount = floor(((float) $this->amount / $count) * 100) / 100;
        $remainingAmount = (float) $this->amount;

        for ($number = 1; $number <= $count; $number++) {
            $amount = $number === $count ? round($remainingAmount, 2) : $baseAmount;
            $remainingAmount = round($remainingAmount - $amount, 2);

            $this->installments()->create([
                'due_date' => $this->return_date->copy()->addMonthsNoOverflow($number - 1),
                'amount' => $amount,
                'status' => $this->status === 'cancelled' ? 'cancelled' : 'pending',
            ]);
        }
    }

    public function syncStatusFromInstallments(): void
    {
        if ($this->status === 'cancelled') {
            return;
        }

        if ($this->installments()->where('status', 'pending')->exists()) {
            if ($this->installments()->where('paid_amount', '>', 0)->exists()) {
                $this->updateQuietly(['status' => 'partially_paid']);

                return;
            }

            $this->updateQuietly(['status' => 'active']);

            return;
        }

        $this->updateQuietly(['status' => 'deducted']);
    }

    public function remainingBalance(): float
    {
        $paidAmount = $this->relationLoaded('installments') ? (float) $this->installments->sum('paid_amount') : (float) $this->installments()->sum('paid_amount');

        return max(0.0, round((float) $this->amount - $paidAmount, 2));
    }
}

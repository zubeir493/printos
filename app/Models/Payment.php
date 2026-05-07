<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;

class Payment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'payment_number',
        'partner_id',
        'bank_id',
        'payment_date',
        'amount',
        'direction',
        'method',
        'reference',
        'transaction_type',
        'payment_type',
        'account_id',
        'expense_account_id',
        'petty_cash_account_id',
        'voided_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'partner_id' => 'integer',
            'bank_id' => 'integer',
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'account_id' => 'integer',
            'expense_account_id' => 'integer',
            'petty_cash_account_id' => 'integer',
            'voided_at' => 'datetime',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function journalEntries(): MorphMany
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }

    public function setCustomerPartnerIdAttribute($value)
    {
        $this->attributes['partner_id'] = $value;
    }

    public function setSupplierPartnerIdAttribute($value)
    {
        $this->attributes['partner_id'] = $value;
    }

    protected static function booted(): void
    {
        static::creating(function (self $payment): void {
            if (! empty($payment->payment_number)) {
                return;
            }

            DB::transaction(function () use ($payment): void {
                $last = self::lockForUpdate()->orderBy('id', 'desc')->first();
                $lastNumber = 0;
                if ($last && preg_match('/PAY-(?:\w+-)?(\d+)/', $last->payment_number, $m)) {
                    $lastNumber = (int) $m[1];
                }
                $payment->payment_number = 'PAY-'.str_pad($lastNumber + 1, 6, '0', STR_PAD_LEFT);
            });
        });
    }
}

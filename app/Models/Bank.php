<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Bank extends Model
{
    use HasFactory;
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->useLogName('finance');
    }

    protected $fillable = [
        'name',
        'code',
        'account_number',
        'account_holder_name',
        'bank_name',
        'branch',
        'opening_balance',
        'current_balance',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'opening_balance' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'status' => 'string',
        ];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function transfersFrom(): HasMany
    {
        return $this->hasMany(BankTransfer::class, 'from_bank_id');
    }

    public function transfersTo(): HasMany
    {
        return $this->hasMany(BankTransfer::class, 'to_bank_id');
    }

    public function cashDeposits(): HasMany
    {
        return $this->hasMany(CashDeposit::class);
    }

    public function getTotalInflowAttribute(): float
    {
        return (float) BankTransaction::query()
            ->where('bank_id', $this->id)
            ->where('balance_delta', '>', 0)
            ->sum('balance_delta');
    }

    public function getTotalOutflowAttribute(): float
    {
        return abs((float) BankTransaction::query()
            ->where('bank_id', $this->id)
            ->where('balance_delta', '<', 0)
            ->sum('balance_delta'));
    }

    public function getExpectedBalanceAttribute(): float
    {
        return round($this->opening_balance + $this->transaction_balance, 2);
    }

    public function getCalculatedBalanceAttribute(): float
    {
        return $this->expected_balance;
    }

    public function getTransactionBalanceAttribute(): float
    {
        return (float) BankTransaction::query()
            ->where('bank_id', $this->id)
            ->sum('balance_delta');
    }

    public function getOpeningBalanceAttribute(): float
    {
        return (float) ($this->attributes['opening_balance']
            ?? round((float) $this->current_balance - $this->transaction_balance, 2));
    }

    public function updateBalance(): void
    {
        $this->update([
            'current_balance' => $this->expected_balance,
        ]);
    }

    protected static function booted(): void
    {
        static::creating(function (self $bank): void {
            $hasOpeningBalance = array_key_exists('opening_balance', $bank->getAttributes());

            if (! array_key_exists('current_balance', $bank->getAttributes())) {
                $bank->setAttribute('current_balance', $hasOpeningBalance ? $bank->getAttributes()['opening_balance'] : 0);
            }

            if (! $hasOpeningBalance) {
                $bank->setAttribute('opening_balance', $bank->current_balance);
            }
        });

        static::saving(function ($model) {
            if ($model->current_balance < 0) {
                throw new \InvalidArgumentException('Bank balance cannot be negative');
            }
        });
    }
}

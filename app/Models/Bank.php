<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bank extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'account_number',
        'account_holder_name',
        'bank_name',
        'branch',
        'current_balance',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
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

    public function getTotalInflowAttribute(): float
    {
        return (float) $this->payments()
            ->where('direction', 'inbound')
            ->sum('amount') +
            (float) $this->transfersTo()
                ->where('status', 'completed')
                ->sum('amount');
    }

    public function getTotalOutflowAttribute(): float
    {
        return (float) $this->payments()
            ->where('direction', 'outbound')
            ->sum('amount') +
            (float) $this->transfersFrom()
                ->where('status', 'completed')
                ->sum('amount');
    }

    public function getExpectedBalanceAttribute(): float
    {
        return $this->total_inflow - $this->total_outflow;
    }

    public function getCalculatedBalanceAttribute(): float
    {
        return $this->expected_balance;
    }

    public function updateBalance(): void
    {
        $this->update([
            'current_balance' => $this->expected_balance,
        ]);
    }

    public static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            if ($model->current_balance < 0) {
                throw new \InvalidArgumentException('Bank balance cannot be negative');
            }
        });
    }
}

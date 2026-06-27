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
            ->selectRaw('COALESCE(SUM(amount - COALESCE(withholding_amount, 0)), 0) as total')
            ->value('total') +
            (float) $this->transfersTo()
                ->where('status', 'completed')
                ->sum('amount');
    }

    public function getTotalOutflowAttribute(): float
    {
        return (float) $this->payments()
            ->where('direction', 'outbound')
            ->selectRaw('COALESCE(SUM(amount - COALESCE(withholding_amount, 0)), 0) as total')
            ->value('total') +
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

    protected static function booted(): void
    {
        static::saving(function ($model) {
            if ($model->current_balance < 0) {
                throw new \InvalidArgumentException('Bank balance cannot be negative');
            }
        });
    }
}

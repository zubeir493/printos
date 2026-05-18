<?php

namespace App\Models;

use App\Support\SequentialNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class BankTransfer extends Model
{
    use HasFactory;
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['transfer_number', 'from_bank_id', 'to_bank_id', 'amount', 'transfer_date', 'status', 'completed_at', 'completed_by'])
            ->logOnlyDirty()
            ->useLogName('bank_transfer');
    }

    protected $fillable = [
        'transfer_number',
        'from_bank_id',
        'to_bank_id',
        'amount',
        'transfer_date',
        'reference',
        'description',
        'status',
        'created_by',
        'completed_by',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'from_bank_id' => 'integer',
            'to_bank_id' => 'integer',
            'amount' => 'decimal:2',
            'transfer_date' => 'date',
            'created_by' => 'integer',
            'completed_by' => 'integer',
            'completed_at' => 'datetime',
            'status' => 'string',
        ];
    }

    public function fromBank(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'from_bank_id');
    }

    public function toBank(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'to_bank_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function complete(?User $user = null): void
    {
        DB::transaction(function () use ($user) {
            if ($this->status !== 'pending') {
                throw new \Exception('Only pending transfers can be completed');
            }

            $this->update([
                'status' => 'completed',
                'completed_by' => $user?->id,
                'completed_at' => now(),
            ]);
        });
    }

    public function cancel(?User $user = null): void
    {
        if ($this->status === 'completed') {
            throw new \Exception('Cannot cancel a completed transfer');
        }

        $this->update([
            'status' => 'cancelled',
            'completed_by' => $user?->id,
            'completed_at' => now(),
        ]);
    }

    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (! $model->transfer_number) {
                $model->transfer_number = SequentialNumber::next(
                    lockName: 'bank_transfers',
                    modelClass: self::class,
                    column: 'transfer_number',
                    prefix: 'BT-',
                    padding: 4,
                    likePattern: 'BT-%',
                );
            }
        });

        static::saving(function ($model) {
            if ($model->amount <= 0) {
                throw new \InvalidArgumentException('Transfer amount must be positive');
            }
            if ($model->from_bank_id === $model->to_bank_id) {
                throw new \InvalidArgumentException('Cannot transfer to the same bank');
            }
        });
    }
}

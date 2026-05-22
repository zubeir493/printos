<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankTransaction extends Model
{
    protected $table = 'bank_transactions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'bank_id' => 'integer',
            'source_id' => 'integer',
            'amount' => 'decimal:2',
            'balance_delta' => 'decimal:2',
            'transaction_date' => 'date',
        ];
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }
}

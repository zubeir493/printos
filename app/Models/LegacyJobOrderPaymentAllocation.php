<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegacyJobOrderPaymentAllocation extends Model
{
    protected $fillable = [
        'legacy_job_order_group_id', 'job_order_id', 'payment_id', 'amount', 'withholding_amount',
    ];

    protected function casts(): array
    {
        return [
            'legacy_job_order_group_id' => 'integer',
            'job_order_id' => 'integer',
            'payment_id' => 'integer',
            'amount' => 'decimal:2',
            'withholding_amount' => 'decimal:2',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(LegacyJobOrderGroup::class, 'legacy_job_order_group_id');
    }

    public function jobOrder(): BelongsTo
    {
        return $this->belongsTo(JobOrder::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}

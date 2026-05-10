<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAllocation extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'payment_id',
        'allocatable_id',
        'allocatable_type',
        'allocated_amount',
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
            'payment_id' => 'integer',
            'allocatable_id' => 'integer',
            'allocated_amount' => 'decimal:2',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function bank()
    {
        return $this->hasOneThrough(Bank::class, Payment::class, 'id', 'id', 'payment_id', 'bank_id');
    }

    public function allocatable()
    {
        return $this->morphTo();
    }

    public function getDocumentNumberAttribute(): ?string
    {
        return match (class_basename($this->allocatable_type ?? '')) {
            'JobOrder' => $this->allocatable?->job_order_number,
            'PurchaseOrder' => $this->allocatable?->po_number,
            'SalesOrder' => $this->allocatable?->order_number,
            default => $this->allocatable_id ? "#{$this->allocatable_id}" : null,
        };
    }
}

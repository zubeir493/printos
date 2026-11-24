<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LegacyJobOrderGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'legacy_parent_id', 'original_job_order_number', 'subtotal', 'tax_amount', 'total',
        'advance_amount', 'advance_paid', 'status', 'original_data',
    ];

    protected function casts(): array
    {
        return [
            'legacy_parent_id' => 'integer',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'advance_amount' => 'decimal:2',
            'advance_paid' => 'boolean',
            'original_data' => 'array',
        ];
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(JobOrder::class, 'legacy_job_order_group_id');
    }

    public function primaryJobOrder(): HasOne
    {
        return $this->hasOne(JobOrder::class, 'id', 'legacy_parent_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LegacyJobOrderItem::class);
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(LegacyJobOrderPaymentAllocation::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'payable_id', 'legacy_parent_id')
            ->where('payable_type', JobOrder::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'order_id', 'legacy_parent_id')
            ->where('order_type', 'job_order');
    }

    public function salesInvoices(): HasMany
    {
        return $this->hasMany(SalesInvoice::class, 'job_order_id', 'legacy_parent_id');
    }

    public function dispatches(): HasMany
    {
        return $this->hasMany(Dispatch::class, 'job_order_id', 'legacy_parent_id');
    }
}

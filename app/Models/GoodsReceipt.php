<?php

namespace App\Models;

use App\Observers\GoodsReceiptObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(GoodsReceiptObserver::class)]
class GoodsReceipt extends Model
{
    protected $fillable = [
        'receipt_number',
        'purchase_order_id',
        'warehouse_id',
        'receipt_date',
        'status',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'receipt_date' => 'date',
            'posted_at' => 'datetime',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }
}

<?php

namespace App\Models;

use App\Observers\GoodsReceiptObserver;
use App\Support\SequentialNumber;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[ObservedBy(GoodsReceiptObserver::class)]
class GoodsReceipt extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['receipt_number', 'purchase_order_id', 'warehouse_id', 'receipt_date', 'status', 'posted_at'])
            ->logOnlyDirty()
            ->useLogName('goods_receipt');
    }

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

    protected static function booted(): void
    {
        static::creating(function (self $receipt): void {
            if (! empty($receipt->receipt_number)) {
                return;
            }

            $receipt->receipt_number = SequentialNumber::next(
                lockName: 'goods_receipts',
                modelClass: self::class,
                column: 'receipt_number',
                prefix: 'GR-',
                padding: 6,
                likePattern: 'GR-%',
            );
        });
    }
}

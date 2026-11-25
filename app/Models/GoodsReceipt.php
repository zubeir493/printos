<?php

namespace App\Models;

use App\Observers\GoodsReceiptObserver;
use App\Support\SequentialNumber;
use App\Support\WarehouseAccess;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[ObservedBy(GoodsReceiptObserver::class)]
class GoodsReceipt extends Model
{
    use LogsActivity;

    public const string DIRECT_MOVEMENT = 'direct_receipt';

    public const string REVERSAL_MOVEMENT = 'direct_receipt_reversal';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['receipt_number', 'purchase_order_id', 'supplier_id', 'source_reference', 'warehouse_id', 'receipt_date', 'status', 'posted_at', 'reversed_at', 'reversed_by', 'reversal_reason'])
            ->logOnlyDirty()
            ->useLogName('goods_receipt');
    }

    protected $fillable = [
        'receipt_number',
        'purchase_order_id',
        'supplier_id',
        'source_reference',
        'direct_reference_key',
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
            'reversed_at' => 'datetime',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'supplier_id');
    }

    public function supplierBill(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function sourceLabel(): string
    {
        return match (true) {
            $this->purchase_order_id !== null => 'Purchase order',
            $this->supplier_id !== null => 'Direct supplier receipt',
            default => 'Opening stock',
        };
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    public function save(array $options = []): bool
    {
        WarehouseAccess::authorizeRecord($this, 'warehouse_id');

        return DB::transaction(fn (): bool => parent::save($options));
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

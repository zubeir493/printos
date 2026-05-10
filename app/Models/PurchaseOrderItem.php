<?php

namespace App\Models;

use App\Observers\PurchaseOrderItemObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(PurchaseOrderItemObserver::class)]
class PurchaseOrderItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'purchase_order_id',
        'inventory_item_id',
        'quantity',
        'received_quantity',
        'unit_price',
        'total',
        'status',
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
            'purchase_order_id' => 'integer',
            'inventory_item_id' => 'integer',
            'quantity' => 'decimal:2',
            'received_quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}

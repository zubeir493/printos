<?php

namespace App\Models;

use App\Observers\SalesOrderItemObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(SalesOrderItemObserver::class)]
class SalesOrderItem extends Model
{
    protected $fillable = [
        'sales_order_id',
        'inventory_item_id',
        'quantity',
        'unit_price',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'sales_order_id' => 'integer',
            'inventory_item_id' => 'integer',
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}

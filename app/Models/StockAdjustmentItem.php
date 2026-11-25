<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustmentItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_adjustment_id',
        'inventory_item_id',
        'system_quantity',
        'adjustment_quantity',
        'new_quantity',
        'difference',
        'is_imported_count',
    ];

    protected static function booted()
    {
        static::creating(function ($item) {
            // Ensure difference is always set to adjustment_quantity
            // as per the delta-based architecture.
            if (is_null($item->difference)) {
                $item->difference = $item->adjustment_quantity ?? 0;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'system_quantity' => 'decimal:2',
            'adjustment_quantity' => 'decimal:2',
            'new_quantity' => 'decimal:2',
            'difference' => 'decimal:2',
            'is_imported_count' => 'boolean',
        ];
    }

    public function stockAdjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}

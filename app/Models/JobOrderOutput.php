<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobOrderOutput extends Model
{
    protected $fillable = [
        'job_order_id',
        'inventory_item_id',
        'warehouse_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'job_order_id' => 'integer',
            'inventory_item_id' => 'integer',
            'warehouse_id' => 'integer',
            'quantity' => 'decimal:2',
        ];
    }

    public function jobOrder(): BelongsTo
    {
        return $this->belongsTo(JobOrder::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    protected static function booted()
    {
        static::created(function ($output) {
            StockMovement::create([
                'inventory_item_id' => $output->inventory_item_id,
                'warehouse_id' => $output->warehouse_id,
                'type' => 'production_output',
                'quantity' => abs($output->quantity),
                'reference_type' => self::class,
                'reference_id' => $output->id,
                'movement_date' => now(),
            ]);
        });
    }
}

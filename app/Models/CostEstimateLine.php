<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CostEstimateLine extends Model
{
    protected $fillable = [
        'cost_estimate_id',
        'category',
        'label',
        'inventory_item_id',
        'quantity',
        'unit',
        'unit_cost',
        'total',
        'snapshot',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'cost_estimate_id' => 'integer',
            'inventory_item_id' => 'integer',
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'total' => 'decimal:2',
            'snapshot' => 'array',
            'sort' => 'integer',
        ];
    }

    public function costEstimate(): BelongsTo
    {
        return $this->belongsTo(CostEstimate::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}

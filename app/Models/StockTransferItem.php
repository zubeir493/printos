<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class StockTransferItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'stock_transfer_id',
        'inventory_item_id',
        'quantity',
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
            'stock_transfer_id' => 'integer',
            'inventory_item_id' => 'integer',
            'quantity' => 'decimal:2',
        ];
    }

    public function save(array $options = []): bool
    {
        return DB::transaction(function () use ($options): bool {
            $transfer = StockTransfer::query()->lockForUpdate()->findOrFail($this->getRawOriginal('stock_transfer_id') ?? $this->stock_transfer_id);
            if ($transfer->status === 'completed' && (! $this->exists || $this->isDirty())) {
                throw new \RuntimeException('Completed stock transfer items cannot be edited.');
            }

            return parent::save($options);
        });
    }

    public function delete(): ?bool
    {
        return DB::transaction(function (): ?bool {
            $transfer = StockTransfer::query()->lockForUpdate()->findOrFail($this->stock_transfer_id);
            if ($transfer->status === 'completed') {
                throw new \RuntimeException('Completed stock transfer items cannot be deleted.');
            }

            return parent::delete();
        });
    }

    public function stockTransfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}

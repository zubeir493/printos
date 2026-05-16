<?php

namespace App\Models;

use App\Support\SequentialNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class StockTransfer extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'transfer_number',
        'from_warehouse_id',
        'to_warehouse_id',
        'transfer_date',
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
            'from_warehouse_id' => 'integer',
            'to_warehouse_id' => 'integer',
            'transfer_date' => 'datetime',
        ];
    }

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    protected static function booted()
    {
        static::creating(function ($transfer) {
            if (! $transfer->transfer_number) {
                $transfer->transfer_number = SequentialNumber::next(
                    lockName: 'stock_transfers',
                    modelClass: self::class,
                    column: 'transfer_number',
                    prefix: 'ST-',
                    padding: 4,
                    likePattern: 'ST-%',
                );
            }
        });

        static::updating(function ($transfer) {
            if ($transfer->isDirty('status') && $transfer->status === 'completed') {
                $transfer->status = $transfer->getOriginal('status');
                $transfer->post();

                return false;
            }
        });
    }

    public function post(): void
    {
        DB::transaction(function () {
            $alreadyPosted = StockMovement::where('reference_type', self::class)
                ->where('reference_id', $this->id)
                ->whereIn('type', ['transfer_out', 'transfer_in'])
                ->exists();

            if ($alreadyPosted) {
                $this->updateQuietly(['status' => 'completed']);

                return;
            }

            foreach ($this->items as $item) {
                $balance = InventoryBalance::where([
                    'inventory_item_id' => $item->inventory_item_id,
                    'warehouse_id' => $this->from_warehouse_id,
                ])->first();

                $available = $balance ? (float) $balance->quantity_on_hand : 0.0;
                $requested = (float) $item->quantity;

                if ($available < $requested) {
                    throw new \Exception(sprintf(
                        'Cannot complete transfer for item %s: only %s available in warehouse %s, requested %s.',
                        $item->inventoryItem?->name ?? $item->inventory_item_id,
                        number_format($available, 2),
                        $this->from_warehouse_id,
                        number_format($requested, 2)
                    ));
                }
            }

            foreach ($this->items as $item) {
                // Outward Movement
                StockMovement::create([
                    'inventory_item_id' => $item->inventory_item_id,
                    'warehouse_id' => $this->from_warehouse_id,
                    'type' => 'transfer_out',
                    'quantity' => -abs($item->quantity),
                    'reference_type' => self::class,
                    'reference_id' => $this->id,
                    'movement_date' => $this->transfer_date ?? now(),
                ]);

                // Inward Movement
                StockMovement::create([
                    'inventory_item_id' => $item->inventory_item_id,
                    'warehouse_id' => $this->to_warehouse_id,
                    'type' => 'transfer_in',
                    'quantity' => abs($item->quantity),
                    'reference_type' => self::class,
                    'reference_id' => $this->id,
                    'movement_date' => $this->transfer_date ?? now(),
                ]);
            }

            $this->updateQuietly(['status' => 'completed']);
        });
    }
}

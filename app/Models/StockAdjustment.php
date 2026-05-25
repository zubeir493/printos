<?php

namespace App\Models;

use App\Support\SequentialNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class StockAdjustment extends Model
{
    use HasFactory;
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['adjustment_number', 'warehouse_id', 'adjustment_date', 'status', 'reason', 'created_by', 'posted_at'])
            ->logOnlyDirty()
            ->useLogName('stock_adjustment');
    }

    protected $fillable = [
        'adjustment_number',
        'warehouse_id',
        'adjustment_date',
        'status',
        'reason',
        'created_by',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'adjustment_date' => 'date',
            'posted_at' => 'datetime',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockAdjustmentItem::class);
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->adjustment_number)) {
                $model->adjustment_number = SequentialNumber::next(
                    lockName: 'stock_adjustments',
                    modelClass: self::class,
                    column: 'adjustment_number',
                    prefix: 'ADJ-',
                    padding: 6,
                    likePattern: 'ADJ-%',
                );
            }
        });

        static::updated(function ($model) {
            if ($model->wasChanged('status') && $model->status === 'posted' && is_null($model->posted_at)) {
                $model->post();
            }
        });
    }

    public function post()
    {
        // If it's already posted, don't do it again
        if (! is_null($this->posted_at)) {
            return;
        }

        if ($this->items()->count() === 0) {
            throw new \Exception('Cannot post an adjustment with no items.');
        }

        $this->validateNonNegativeAdjustment();

        DB::transaction(function () {
            foreach ($this->items as $item) {
                if ((float) $item->adjustment_quantity === 0.0) {
                    continue;
                }

                StockMovement::create([
                    'inventory_item_id' => $item->inventory_item_id,
                    'warehouse_id' => $this->warehouse_id,
                    'type' => 'adjustment',
                    'quantity' => $item->adjustment_quantity,
                    'reference_type' => self::class,
                    'reference_id' => $this->id,
                    'movement_date' => now(), // today
                ]);
            }

            $this->updateQuietly([
                'status' => 'posted',
                'posted_at' => now(),
            ]);
        });
    }

    private function validateNonNegativeAdjustment(): void
    {
        $itemIds = $this->items->pluck('inventory_item_id')->unique()->toArray();

        $balances = InventoryBalance::whereIn('inventory_item_id', $itemIds)
            ->where('warehouse_id', $this->warehouse_id)
            ->get()
            ->keyBy('inventory_item_id');

        foreach ($this->items as $item) {
            if ((float) $item->adjustment_quantity >= 0.0) {
                continue;
            }

            $balance = $balances->get($item->inventory_item_id);

            $startingQty = $balance ? (float) $balance->quantity_on_hand : 0.0;
            $resultingQty = $startingQty + (float) $item->adjustment_quantity;

            if ($resultingQty < -0.00001) {
                throw new \Exception(sprintf(
                    'Cannot post stock adjustment because item %s would go negative (current %s, adjustment %s).',
                    $item->inventoryItem?->name ?? 'Unknown Item',
                    number_format($startingQty, 2),
                    number_format($item->adjustment_quantity, 2)
                ));
            }
        }
    }
}

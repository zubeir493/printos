<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class SalesOrder extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->useLogName('sales_order');
    }

    protected $fillable = [
        'order_number',
        'warehouse_id',
        'partner_id',
        'order_date',
        'due_date',
        'payment_mode',
        'payment_method',
        'payment_reference',
        'subtotal',
        'tax_amount',
        'total',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'warehouse_id' => 'integer',
            'partner_id' => 'integer',
            'order_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function salesOrderItems(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    public function paymentAllocations(): MorphMany
    {
        return $this->morphMany(PaymentAllocation::class, 'allocatable');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'order_id')
            ->where('order_type', 'sales_order');
    }

    public function getPaidAmountAttribute(): float
    {
        return (float) $this->paymentAllocations()->sum('allocated_amount');
    }

    public function getBalanceAttribute(): float
    {
        return (float) ($this->total - $this->paid_amount);
    }

    public function recalculateTotal(): void
    {
        $subtotal = (float) $this->salesOrderItems()->sum('total');
        $taxRate = $this->getTaxRate();
        $taxAmount = round($subtotal * $taxRate, 2);

        $this->updateQuietly([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $subtotal + $taxAmount,
        ]);
    }

    private function getTaxRate(): float
    {
        $settings = Setting::getSettings();

        return $settings->vat_enabled ? (float) $settings->vat_rate / 100 : 0.0;
    }

    public function isCashSale(): bool
    {
        return $this->payment_mode === 'cash';
    }

    protected static function booted()
    {
        static::creating(function ($salesOrder): void {
            if (! empty($salesOrder->order_number)) {
                return;
            }

            DB::transaction(function () use ($salesOrder): void {
                $last = self::lockForUpdate()->orderBy('id', 'desc')->first();
                $nextId = $last ? $last->id + 1 : 1;
                $salesOrder->order_number = 'SO-'.str_pad($nextId, 5, '0', STR_PAD_LEFT);
            });
        });

        static::updating(function ($salesOrder) {
            if ($salesOrder->isDirty('status') && $salesOrder->status === 'completed') {
                // Validate sufficient stock
                foreach ($salesOrder->salesOrderItems as $item) {
                    $balance = InventoryBalance::where('inventory_item_id', $item->inventory_item_id)
                        ->where('warehouse_id', $salesOrder->warehouse_id)
                        ->first();
                    $qty = $balance ? (float) $balance->quantity_on_hand : 0;
                    if ($qty < (float) $item->quantity) {
                        $itemName = $item->inventoryItem ? $item->inventoryItem->name : 'Unknown Item';
                        throw new \Exception("Insufficient stock for item {$itemName}. Available: {$qty}, Required: {$item->quantity}");
                    }
                }
            }
        });

        static::updated(function ($salesOrder) {
            if ($salesOrder->wasChanged('status') && $salesOrder->status === 'completed') {
                DB::transaction(function () use ($salesOrder) {
                    foreach ($salesOrder->salesOrderItems as $item) {
                        // Prevent duplicate movements
                        $exists = StockMovement::where('reference_type', self::class)
                            ->where('reference_id', $salesOrder->id)
                            ->where('inventory_item_id', $item->inventory_item_id)
                            ->exists();

                        if (! $exists) {
                            StockMovement::create([
                                'inventory_item_id' => $item->inventory_item_id,
                                'warehouse_id' => $salesOrder->warehouse_id,
                                'type' => 'sale',
                                'reference_type' => self::class,
                                'reference_id' => $salesOrder->id,
                                'quantity' => -abs($item->quantity),
                                'movement_date' => now(),
                            ]);
                        }
                    }
                });
            }
        });
    }
}

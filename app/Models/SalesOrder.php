<?php

namespace App\Models;

use App\Support\SequentialNumber;
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

    public const STATUS_DRAFT = 'draft';

    public const STATUS_DEPOSIT_RECEIVED = 'deposit_received';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_VOID = 'void';

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_DEPOSIT_RECEIVED => 'Deposit Received',
            self::STATUS_SUBMITTED => 'Submitted',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_VOID => 'Void',
        ];
    }

    public static function statusLabel(?string $status): string
    {
        return self::statusOptions()[$status] ?? str((string) $status)->headline()->toString();
    }

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
        'bank_id',
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
            'bank_id' => 'integer',
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

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function salesOrderItems(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'order_id')
            ->where('order_type', 'sales_order');
    }

    public function getPaidAmountAttribute(): float
    {
        return (float) $this->payments()->whereNull('voided_at')->sum('amount');
    }

    public function getBalanceAttribute(): float
    {
        return (float) ($this->total - $this->paid_amount);
    }

    public function recalculateTotals(): void
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

    public function hasSubmittedItems(): bool
    {
        return in_array($this->status, [self::STATUS_SUBMITTED, self::STATUS_COMPLETED], true);
    }

    public function isPaidInFull(): bool
    {
        return $this->balance <= 0.00001;
    }

    private function remainingBalanceForCompletion(): float
    {
        $total = (float) ($this->getAttribute('total') ?: $this->getOriginal('total') ?: $this->fresh()?->total ?: 0);
        $paid = (float) $this->payments()->whereNull('voided_at')->sum('amount');

        return $total - $paid;
    }

    public function save(array $options = []): bool
    {
        return DB::transaction(function () use ($options): bool {
            if ($this->exists) {
                $changes = $this->getDirty();
                $current = self::query()->lockForUpdate()->findOrFail($this->id);
                $this->setRawAttributes($current->getAttributes(), true);
                $this->setRawAttributes(array_replace($this->getAttributes(), $changes));
                $this->unsetRelations();
            }

            return parent::save($options);
        });
    }

    protected static function booted()
    {
        static::creating(function ($salesOrder): void {
            if (! empty($salesOrder->order_number)) {
                return;
            }

            $salesOrder->order_number = SequentialNumber::next(
                lockName: 'sales_orders',
                modelClass: self::class,
                column: 'order_number',
                prefix: 'SO-',
                padding: 6,
                likePattern: 'SO-%',
            );
        });

        static::updating(function ($salesOrder) {
            if (
                $salesOrder->isDirty('status') &&
                in_array($salesOrder->status, [self::STATUS_SUBMITTED, self::STATUS_COMPLETED], true)
            ) {
                if (
                    $salesOrder->status === self::STATUS_COMPLETED &&
                    ! $salesOrder->isCashSale() &&
                    $salesOrder->remainingBalanceForCompletion() > 0.00001
                ) {
                    throw new \Exception('Credit sales can only be completed after full payment is received.');
                }

                if (in_array($salesOrder->getOriginal('status'), [self::STATUS_SUBMITTED, self::STATUS_COMPLETED], true)) {
                    return;
                }
                $salesOrder->load('salesOrderItems.inventoryItem');
                $requirements = $salesOrder->salesOrderItems->groupBy('inventory_item_id');
                foreach ($requirements as $inventoryItemId => $items) {
                    $requiredBase = $items->sum(fn (SalesOrderItem $item): float => $item->baseQuantityForStockMovement());
                    $balance = InventoryBalance::where('inventory_item_id', $inventoryItemId)
                        ->where('warehouse_id', $salesOrder->warehouse_id)->lockForUpdate()->first();
                    $available = $balance ? (float) $balance->quantity_on_hand : 0;
                    if ($requiredBase <= 0 || $available < $requiredBase) {
                        throw new \RuntimeException('Insufficient stock for item '.($items->first()->inventoryItem?->name ?? $inventoryItemId).'. Available: '.$available.', Required: '.$requiredBase.'.');
                    }
                }
            }
        });

        static::updated(function ($salesOrder) {
            if (
                $salesOrder->wasChanged('status') &&
                in_array($salesOrder->status, [self::STATUS_SUBMITTED, self::STATUS_COMPLETED], true)
            ) {
                DB::transaction(function () use ($salesOrder) {
                    $salesOrder->loadMissing('salesOrderItems.inventoryItem');

                    $existingItemIds = StockMovement::where('reference_type', self::class)
                        ->where('reference_id', $salesOrder->id)
                        ->pluck('inventory_item_id')
                        ->toArray();

                    foreach ($salesOrder->salesOrderItems->groupBy('inventory_item_id') as $inventoryItemId => $items) {
                        if (in_array((int) $inventoryItemId, $existingItemIds, true)) {
                            continue;
                        }
                        $baseQty = $items->sum(fn (SalesOrderItem $item): float => $item->baseQuantityForStockMovement());
                        StockMovement::create([
                            'inventory_item_id' => $inventoryItemId,
                            'warehouse_id' => $salesOrder->warehouse_id,
                            'type' => 'sale',
                            'reference_type' => self::class,
                            'reference_id' => $salesOrder->id,
                            'quantity' => -abs($baseQty),
                            'movement_date' => now(),
                        ]);
                    }
                });
            }
        });
    }
}

<?php

namespace App\Models;

use App\Support\SequentialNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PurchaseOrder extends Model
{
    use HasFactory;
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->useLogName('purchase_order');
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'po_number',
        'partner_id',
        'order_date',
        'due_date',
        'status',
        'subtotal',
        'tax_amount',
        'total',
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
            'partner_id' => 'integer',
            'order_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'short_close_subtotal' => 'decimal:2',
            'short_close_tax' => 'decimal:2',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'order_id')
            ->where('order_type', 'purchase_order');
    }

    public function getPaidAmountAttribute(): float
    {
        return (float) $this->payments()->whereNull('voided_at')->sum('amount');
    }

    public function getBalanceAttribute(): float
    {
        if (array_key_exists('balance', $this->getAttributes())) {
            return (float) $this->getRawOriginal('balance');
        }

        return $this->payableTotal() - $this->paid_amount;
    }

    public function payableTotal(): float
    {
        return $this->short_close_subtotal === null
            ? (float) $this->total
            : round((float) $this->short_close_subtotal + (float) $this->short_close_tax, 2);
    }

    public function closeRemainingQuantity(): void
    {
        DB::transaction(function (): void {
            $order = self::query()->lockForUpdate()->findOrFail($this->id);
            $items = $order->purchaseOrderItems()->lockForUpdate()->get();
            $received = round($items->sum(fn (PurchaseOrderItem $item): float => (float) $item->received_quantity * (float) $item->unit_price), 2);

            if ($order->status !== 'approved' || ! $items->contains(fn (PurchaseOrderItem $item): bool => (float) $item->received_quantity > 0)
                || ! $order->goodsReceipts()->whereNotNull('posted_at')->exists()) {
                throw ValidationException::withMessages(['status' => 'Post a receipt for this approved order before closing the unreceived balance.']);
            }

            $tax = (float) $order->subtotal > 0 ? round($received * (float) $order->tax_amount / (float) $order->subtotal, 2) : 0.0;
            if (round($order->paid_amount, 2) > round($received + $tax, 2)) {
                throw ValidationException::withMessages(['status' => 'Resolve the supplier overpayment before closing the remaining quantity.']);
            }
            if ($order->invoices()->where('status', '!=', 'cancelled')->where('total_amount', '!=', round($received + $tax, 2))->exists()) {
                throw ValidationException::withMessages(['status' => 'Resolve the existing purchase invoice before closing the remaining quantity.']);
            }

            $order->forceFill(['short_close_subtotal' => $received, 'short_close_tax' => $tax, 'status' => 'received'])->save();
        }, 3);
    }

    public function recalculateTotals(): void
    {
        $subtotal = (float) $this->purchaseOrderItems()->sum('total');
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

    public function save(array $options = []): bool
    {
        return DB::transaction(function () use ($options): bool {
            if ($this->exists) {
                $current = self::query()->lockForUpdate()->findOrFail($this->id);
                if ($this->isDirty('status') && $this->status === 'cancelled'
                    && ($current->goodsReceipts()->whereNotNull('posted_at')->exists() || $current->paid_amount > 0)) {
                    throw ValidationException::withMessages(['status' => 'Orders with posted receipts or payments cannot be cancelled. Close the remaining quantity instead.']);
                }
                if (($current->status === 'received' || $current->goodsReceipts()->whereNotNull('posted_at')->exists())
                    && $this->isDirty(['partner_id', 'order_date', 'subtotal', 'tax_amount', 'total'])) {
                    throw new \RuntimeException('Received purchase orders cannot be edited.');
                }
            }

            return parent::save($options);
        });
    }

    protected static function booted()
    {
        static::creating(function ($po): void {
            if (! empty($po->po_number)) {
                return;
            }

            $po->po_number = SequentialNumber::next(
                lockName: 'purchase_orders',
                modelClass: self::class,
                column: 'po_number',
                prefix: 'PO-',
                padding: 6,
                likePattern: 'PO-%',
            );
        });

        static::updating(function ($po) {
            //
        });

        static::updated(function ($po) {
            if ($po->wasChanged('status') && $po->status === 'cancelled') {
                $po->purchaseOrderItems()->update(['status' => 'cancelled']);
            }
        });
    }
}

<?php

namespace App\Models;

use App\Support\SequentialNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
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

    public function getPaidAmountAttribute(): float
    {
        return (float) $this->payments()->whereNull('voided_at')->sum('amount');
    }

    public function getBalanceAttribute(): float
    {
        return (float) (($this->total ?? 0) - $this->paid_amount);
    }

    public function recalculateSubtotal(): void
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
                padding: 4,
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

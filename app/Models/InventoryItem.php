<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class InventoryItem extends Model
{
    use HasFactory;
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->useLogName('inventory');
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'sku',
        'image',
        'unit',
        'purchase_unit',
        'conversion_factor',
        'type',
        'category',
        'is_sellable',
        'price',
        'average_cost',
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
            'is_sellable' => 'boolean',
            'price' => 'decimal:2',
        ];
    }

    public function inventoryBalances(): HasMany
    {
        return $this->hasMany(InventoryBalance::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Whether this item has a purchase unit distinct from its base unit.
     */
    public function hasPurchaseUnit(): bool
    {
        return filled($this->purchase_unit) && (float) ($this->conversion_factor ?? 0) > 0;
    }

    /**
     * Convert a base-unit quantity to purchase units.
     * e.g. 2500 sheets → 5 reams (factor = 500)
     */
    public function toPurchaseUnits(float $baseQty): float
    {
        if (! $this->hasPurchaseUnit()) {
            return $baseQty;
        }

        return $baseQty / (float) $this->conversion_factor;
    }

    /**
     * Convert a purchase-unit quantity to base units.
     * e.g. 5 reams → 2500 sheets (factor = 500)
     */
    public function toBaseUnits(float $purchaseQty): float
    {
        if (! $this->hasPurchaseUnit()) {
            return $purchaseQty;
        }

        return $purchaseQty * (float) $this->conversion_factor;
    }

    /**
     * Price per purchase unit (derived from average_cost if available, else price).
     * price is stored as the per-purchase-unit price when a purchase_unit exists,
     * or as the per-base-unit price for regular items.
     */
    public function pricePerPurchaseUnit(): float
    {
        if ($this->hasPurchaseUnit()) {
            // For items with a purchase unit, use average_cost per base unit × factor
            // to get per-purchase-unit cost. If average_cost is zero, fall back to price
            // which is stored as the per-purchase-unit price directly.
            $avgCost = (float) ($this->average_cost ?? 0);

            if ($avgCost > 0) {
                return $avgCost * (float) $this->conversion_factor;
            }

            // price is stored per purchase unit for raw materials
            return (float) ($this->price ?? 0);
        }

        return (float) ($this->average_cost > 0 ? $this->average_cost : ($this->price ?? 0));
    }

    /**
     * The unit label to display for a given quantity context.
     */
    public function displayUnit(bool $purchaseContext = false): string
    {
        if ($purchaseContext && $this->hasPurchaseUnit()) {
            return $this->purchase_unit;
        }

        return $this->unit ?? '';
    }
}

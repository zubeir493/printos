<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class InventoryItem extends Model
{
    use HasFactory;
    use LogsActivity;

    public const array RAW_MATERIAL_CATEGORIES = [
        'paper' => 'Paper',
        'board' => 'Board',
        'ink' => 'Ink',
        'adhesive' => 'Adhesive',
        'liner' => 'Liner',
        'lamination' => 'Lamination',
        'coating' => 'Coating',
        'glue' => 'Glue',
        'packing' => 'Packing',
        'other' => 'Other',
    ];

    public const array FINISHED_GOOD_CATEGORIES = [
        'quran' => 'Quran',
        'hadeeth' => 'Hadeeth',
        'aqeedah' => 'Aqeedah',
        'fiqh' => 'Fiqh',
        'history' => 'History',
        'external' => 'External',
    ];

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
        'low_stock_threshold',
        'gsm',
        'width',
        'height',
        'default_waste_percent',
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
            'low_stock_threshold' => 'decimal:2',
            'gsm' => 'decimal:2',
            'width' => 'decimal:3',
            'height' => 'decimal:3',
            'default_waste_percent' => 'decimal:2',
        ];
    }

    public function stockOnHand(): float
    {
        return (float) $this->inventoryBalances()->sum('quantity_on_hand');
    }

    public function scopeRawMaterials(Builder $query): Builder
    {
        return $query->where('type', 'raw_material');
    }

    public function scopeMaterialCategory(Builder $query, string|array $categories): Builder
    {
        return $query->rawMaterials()->whereIn('category', (array) $categories);
    }

    public function scopePaperMaterials(Builder $query): Builder
    {
        return $query->materialCategory('paper');
    }

    public function scopeBoardMaterials(Builder $query): Builder
    {
        return $query->materialCategory('board');
    }

    public function scopePaperOrBoardMaterials(Builder $query): Builder
    {
        return $query->materialCategory(['paper', 'board']);
    }

    public function scopeInkMaterials(Builder $query): Builder
    {
        return $query->materialCategory('ink');
    }

    public function scopeAdhesiveMaterials(Builder $query): Builder
    {
        return $query->materialCategory('adhesive');
    }

    public function scopeLinerMaterials(Builder $query): Builder
    {
        return $query->materialCategory('liner');
    }

    public function scopeLaminationMaterials(Builder $query): Builder
    {
        return $query->materialCategory('lamination');
    }

    public function scopeCoatingMaterials(Builder $query): Builder
    {
        return $query->materialCategory('coating');
    }

    public function scopeGlueMaterials(Builder $query): Builder
    {
        return $query->materialCategory('glue');
    }

    public function scopePackingMaterials(Builder $query): Builder
    {
        return $query->materialCategory('packing');
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
     * Cost normalized to the stock/base unit.
     */
    public function baseUnitCost(): float
    {
        $averageCost = (float) ($this->average_cost ?? 0);

        if ($averageCost > 0) {
            return $averageCost;
        }

        if ($this->hasPurchaseUnit()) {
            return (float) ($this->price ?? 0) / (float) $this->conversion_factor;
        }

        return (float) ($this->price ?? 0);
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

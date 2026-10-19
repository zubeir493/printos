<?php

namespace App\Models;

use App\Observers\JobOrderObserver;
use App\Services\JobOrders\JobOrderWorkflow;
use App\States\JobOrder\JobOrderState;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\ModelStates\HasStates;

#[ObservedBy(JobOrderObserver::class)]
class JobOrder extends Model
{
    use HasFactory;
    use HasStates;
    use LogsActivity;
    use SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->useLogName('job_order');
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'quantity', 'size', 'paper', 'deliverables', 'instructions',
        'designer_id', 'typist_id', 'proforma_id', 'proforma_task_id', 'output_inventory_item_id',
        'design_completed_at', 'production_completed_at', 'completed_at',
        'job_order_number',
        'cost_calc_file',
        'partner_id',
        'job_type',
        'production_mode',
        'services',
        'submission_date',
        'due_date',
        'remarks',
        'advance_paid',
        'advance_amount',
        'subtotal',
        'tax_amount',
        'total',
        'status',
        'notified_late_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer', 'paper' => 'array', 'deliverables' => 'array',
            'design_completed_at' => 'datetime', 'production_completed_at' => 'datetime', 'completed_at' => 'datetime',
            'designer_id' => 'integer', 'typist_id' => 'integer', 'proforma_id' => 'integer', 'proforma_task_id' => 'integer', 'output_inventory_item_id' => 'integer',
            'id' => 'integer',
            'partner_id' => 'integer',
            'submission_date' => 'date',
            'due_date' => 'date',
            'advance_amount' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'services' => 'json',
            'advance_paid' => 'boolean',
            'status' => JobOrderState::class,
            'notified_late_at' => 'datetime',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function proforma(): BelongsTo
    {
        return $this->belongsTo(Proforma::class);
    }

    public function proformaTask(): BelongsTo
    {
        return $this->belongsTo(ProformaTask::class);
    }

    public function designer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'designer_id');
    }

    public function typist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'typist_id');
    }

    public function outputInventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'output_inventory_item_id');
    }

    public function dispatchItems(): HasMany
    {
        return $this->hasMany(DispatchItem::class);
    }

    public function productionPlanItems(): HasMany
    {
        return $this->hasMany(ProductionPlanItem::class);
    }

    public function textFiles(): HasMany
    {
        return $this->hasMany(TextFile::class);
    }

    public function dielines(): HasMany
    {
        return $this->hasMany(Dieline::class);
    }

    public function productionMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'reference')->where('type', 'production_output');
    }

    public function scopeInProgress(Builder $query): Builder
    {
        return $query->whereIn('status', ['design', 'production']);
    }

    public function scopeClientReceivables(Builder $query): Builder
    {
        return $query->where('production_mode', 'make_to_order')->whereIn('status', ['design', 'production', 'completed']);
    }

    public function dispatches(): HasMany
    {
        return $this->hasMany(Dispatch::class);
    }

    public function salesInvoices(): HasMany
    {
        return $this->hasMany(SalesInvoice::class);
    }

    public function materialMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }

    public function materialRequests(): HasMany
    {
        return $this->hasMany(MaterialRequest::class);
    }

    public function artworks(): HasMany
    {
        return $this->hasMany(Artwork::class);
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'order_id')
            ->where('order_type', 'job_order');
    }

    public function getPaidAmountAttribute(): float
    {
        return (float) $this->payments()->whereNull('voided_at')->sum('amount');
    }

    public function getBalanceAttribute(): float
    {
        return (float) ($this->total - $this->paid_amount);
    }

    public function scopePendingPayment($query)
    {
        return $query->whereRaw('total > (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payable_id = job_orders.id AND payable_type = ? AND voided_at IS NULL)', [self::class]);
    }

    public function scopeFullyPaid($query)
    {
        return $query->whereRaw('total <= (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payable_id = job_orders.id AND payable_type = ? AND voided_at IS NULL)', [self::class]);
    }

    public function scopeLate($query, CarbonInterface|string|null $date = null)
    {
        $date ??= today();

        return $query
            ->whereDate('submission_date', '<', $date)
            ->whereNotIn('status', ['completed', 'cancelled']);
    }

    public function recalculateTotals(): void
    {
        $subtotal = (float) $this->subtotal;
        $taxRate = $this->getTaxRate();
        $taxAmount = ($this->proforma_task_id || (string) $this->status !== 'draft') ? (float) $this->tax_amount : round($subtotal * $taxRate, 2);

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

    public function issuedQuantityFor($itemId): float
    {
        $sum = (float) StockMovement::where(function ($query) {
            $query->where('type', 'consumption')
                ->orWhere('type', 'material_return');
        })
            ->where('reference_type', self::class)
            ->where('reference_id', $this->id)
            ->where('inventory_item_id', $itemId)
            ->sum('quantity');

        return round(abs($sum), 4);
    }

    public function issuedBalanceByWarehouse(): Collection
    {
        return StockMovement::where(function ($query) {
            $query->where('type', 'consumption')
                ->orWhere('type', 'material_return');
        })
            ->where('reference_type', self::class)
            ->where('reference_id', $this->id)
            ->select('warehouse_id', 'inventory_item_id')
            ->selectRaw('SUM(quantity) as net_quantity')
            ->groupBy('warehouse_id', 'inventory_item_id')
            ->get()
            ->groupBy('warehouse_id');
    }

    public function overConsumedQuantity($itemId): float
    {
        $required = collect();

        foreach ($this->paper ?? [] as $material) {
            if ($material['inventory_item_id'] == $itemId) {
                $required->push(($material['required_quantity'] ?? 0) + ($material['reserve_quantity'] ?? 0));
            }
        }

        $issued = $this->issuedQuantityFor($itemId);

        return max(0, $issued - $required->sum());
    }

    public function materialsCompletionPercentage(): float
    {
        $summary = $this->materials_summary;

        if (empty($summary)) {
            return 0;
        }

        $totalRequired = collect($summary)->sum('required');
        $totalIssued = collect($summary)->sum('issued');

        if ($totalRequired == 0) {
            return 0;
        }

        return ($totalIssued / $totalRequired) * 100;
    }

    public function syncCompletionStatus(): bool
    {
        return app(JobOrderWorkflow::class)->sync($this);
    }

    public function updateStatus(): void
    {
        $this->syncCompletionStatus();
    }

    public function isFullyPaid(): bool
    {
        return $this->balance <= 0.001;
    }

    public function productionIsComplete(): bool
    {
        return $this->quantity > 0 && $this->produced_quantity >= $this->quantity
            && (empty($this->deliverables) || $this->isReadyForProduction());
    }

    public function canComplete(): bool
    {
        return $this->productionIsComplete() && ($this->production_mode === 'make_to_stock' || $this->isFullyPaid());
    }

    public function getProducedQuantityAttribute(): float
    {
        if ($this->relationLoaded('productionMovements')) {
            return (float) $this->productionMovements->sum('quantity');
        }

        if (array_key_exists('production_movements_sum_quantity', $this->getAttributes())) {
            return (float) $this->getAttribute('production_movements_sum_quantity');
        }

        return (float) $this->productionMovements()->sum('quantity');
    }

    public function getRemainingProductionQuantityAttribute(): float
    {
        return max(0, (float) $this->quantity - $this->produced_quantity);
    }

    public function getRemainingQuantityAttribute(): float
    {
        return max(0, $this->produced_quantity - (float) $this->dispatchItems()->sum('quantity'));
    }

    /** @return array<string, string> */
    public function deliverableOptionsForType(string $type): array
    {
        return collect($this->deliverables ?? [])->filter(fn ($item): bool => is_array($item)
            && ($item['type'] ?? null) === $type && filled($item['label'] ?? null))
            ->mapWithKeys(fn (array $item): array => [$item['label'] => $item['label']])->all();
    }

    public function isReadyForProduction(): bool
    {
        if (empty($this->deliverables)) {
            return $this->artworks()->where('is_approved', true)->exists()
                || $this->textFiles()->where('is_approved', true)->exists();
        }

        foreach ($this->deliverables as $deliverable) {
            $type = $deliverable['type'] ?? null;
            $label = trim((string) ($deliverable['label'] ?? ''));
            if (! in_array($type, ['artwork', 'text_file'], true) || $label === '') {
                return false;
            }
            $files = $type === 'artwork' ? $this->artworks() : $this->textFiles();
            if (! $files->where('is_approved', true)->where('deliverable', $label)->exists()) {
                return false;
            }
        }

        return true;
    }

    public function canStartProduction(): bool
    {
        return (string) $this->status === 'design' && $this->isReadyForProduction();
    }

    public function cancel(): void
    {
        app(JobOrderWorkflow::class)->transition($this, 'cancelled');
    }

    public function getMaterialsSummaryAttribute(): array
    {
        $allPaper = collect($this->paper ?? []);
        $uniqueMaterialIds = $allPaper->pluck('inventory_item_id')->unique()->filter()->toArray();

        if (empty($uniqueMaterialIds)) {
            return [];
        }

        // 1. Bulk fetch all relevant Inventory Items
        $items = InventoryItem::whereIn('id', $uniqueMaterialIds)->get()->keyBy('id');

        // 2. Bulk fetch all relevant Stock Movements for this JO
        $movements = StockMovement::where(function ($query) {
            $query->where('type', 'consumption')
                ->orWhere('type', 'material_return');
        })
            ->where('reference_type', self::class)
            ->where('reference_id', $this->id)
            ->whereIn('inventory_item_id', $uniqueMaterialIds)
            ->get()
            ->groupBy('inventory_item_id');

        $summary = [];

        foreach ($uniqueMaterialIds as $itemId) {
            $item = $items->get($itemId);
            if (! $item) {
                continue;
            }

            $required = (float) $allPaper->where('inventory_item_id', $itemId)
                ->sum(fn ($p) => ($p['required_quantity'] ?? 0) + ($p['reserve_quantity'] ?? 0));

            // Calculate issued quantity from bulk movements
            $itemMovements = $movements->get($itemId, collect());
            $issued = round(abs((float) $itemMovements->sum('quantity')), 4);

            $overconsumed = max(0, $issued - $required);
            $remaining = max(0, $required - $issued);
            $completion = $required > 0 ? min(100, ($issued / $required) * 100) : 0;

            $summary[] = [
                'material_name' => $item->name,
                'required' => $required,
                'issued' => $issued,
                'remaining' => $remaining,
                'overconsumed' => $overconsumed,
                'completion' => round($completion, 2),
                // Purchase-unit display fields
                'purchase_unit' => $item->hasPurchaseUnit() ? $item->purchase_unit : $item->unit,
                'remaining_purchase_qty' => $item->hasPurchaseUnit()
                    ? round($item->toPurchaseUnits($remaining), 4)
                    : $remaining,
                'price_per_purchase_unit' => $item->pricePerPurchaseUnit(),
                'inventory_item_id' => $itemId,
            ];
        }

        return $summary;
    }
}

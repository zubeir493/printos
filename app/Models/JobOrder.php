<?php

namespace App\Models;

use App\Observers\JobOrderObserver;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

#[ObservedBy(JobOrderObserver::class)]
class JobOrder extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'job_order_number',
        'partner_id',
        'job_type',
        'production_mode',
        'services',
        'submission_date',
        'due_date',
        'remarks',
        'advance_paid',
        'cost_calc_file',
        'advance_amount',
        'subtotal',
        'tax_amount',
        'total',
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
            'partner_id' => 'integer',
            'submission_date' => 'date',
            'due_date' => 'date',
            'advance_amount' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'services' => 'json',
            'advance_paid' => 'boolean',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function jobOrderTasks(): HasMany
    {
        return $this->hasMany(JobOrderTask::class);
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

    public function materialRequests(): HasManyThrough
    {
        return $this->hasManyThrough(MaterialRequest::class, JobOrderTask::class);
    }

    public function artworks(): HasManyThrough
    {
        return $this->hasManyThrough(Artwork::class, JobOrderTask::class);
    }

    public function paymentAllocations(): MorphMany
    {
        return $this->morphMany(PaymentAllocation::class, 'allocatable');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'order_id')
            ->where('order_type', 'job_order');
    }

    public function getPaidAmountAttribute(): float
    {
        return (float) $this->paymentAllocations()->sum('allocated_amount');
    }

    public function getBalanceAttribute(): float
    {
        return (float) ($this->total - $this->paid_amount);
    }

    public function scopePendingPayment($query)
    {
        return $query->whereRaw('total > (SELECT COALESCE(SUM(allocated_amount), 0) FROM payment_allocations WHERE allocatable_id = job_orders.id AND allocatable_type = ?)', [self::class]);
    }

    public function scopeFullyPaid($query)
    {
        return $query->whereRaw('total <= (SELECT COALESCE(SUM(allocated_amount), 0) FROM payment_allocations WHERE allocatable_id = job_orders.id AND allocatable_type = ?)', [self::class]);
    }

    public function scopeLate($query, CarbonInterface|string|null $date = null)
    {
        $date ??= today();

        return $query
            ->whereDate('submission_date', '<', $date)
            ->whereNotIn('status', ['completed', 'cancelled']);
    }

    public function recalculateTotal(): void
    {
        $subtotal = (float) $this->jobOrderTasks()->sum('task_cost');
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

    public function canStartProduction(): bool
    {
        if ($this->artworks()->count() === 0) {
            return false;
        }

        return ! $this->artworks()
            ->where('is_approved', false)
            ->exists();
    }

    public function issuedQuantityFor($itemId): float
    {
        $sum = (float) StockMovement::where(function ($query) {
            $query->where('type', 'consumption')
                ->orWhere('type', 'material_return');
        })
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

        foreach ($this->jobOrderTasks as $task) {
            foreach ($task->paper ?? [] as $material) {
                if ($material['inventory_item_id'] == $itemId) {
                    $required->push(($material['required_quantity'] ?? 0) + ($material['reserve_quantity'] ?? 0));
                }
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

    public function getMaterialsSummaryAttribute(): array
    {
        if (! $this->relationLoaded('jobOrderTasks')) {
            $this->load('jobOrderTasks');
        }

        $allPaper = $this->jobOrderTasks->flatMap->paper;
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
            ];
        }

        return $summary;
    }
}

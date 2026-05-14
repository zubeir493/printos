<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class JobOrderTask extends Model
{
    use HasFactory;
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'designer_id', 'quantity', 'task_cost', 'status', 'size'])
            ->logOnlyDirty()
            ->useLogName('job_order_task');
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'job_order_id',
        'designer_id',
        'name',
        'quantity',
        'task_cost',
        'paper',
        'status',
        'size',
        'instructions',
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
            'job_order_id' => 'integer',
            'designer_id' => 'integer',
            'task_cost' => 'decimal:2',
            'paper' => 'array',
        ];
    }

    public function jobOrder(): BelongsTo
    {
        return $this->belongsTo(JobOrder::class);
    }

    public function designer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'designer_id');
    }

    public function dispatchItems(): HasMany
    {
        return $this->hasMany(DispatchItem::class);
    }

    public function productionPlanItems(): HasMany
    {
        return $this->hasMany(ProductionPlanItem::class);
    }

    public function getProducedQuantityAttribute(): int|float
    {
        return (float) StockMovement::where('reference_type', static::class)
            ->where('reference_id', $this->id)
            ->where('type', 'production_output')
            ->sum('quantity');
    }

    public function getRemainingProductionQuantityAttribute(): int|float
    {
        return max(0, (float) $this->quantity - $this->produced_quantity);
    }

    public function getRemainingQuantityAttribute(): int|float
    {
        return $this->produced_quantity - $this->dispatchItems()->sum('quantity');
    }

    public function materialRequests(): HasMany
    {
        return $this->hasMany(MaterialRequest::class);
    }

    public function artworks(): HasMany
    {
        return $this->hasMany(Artwork::class);
    }

    /**
     * Update task status based on current conditions
     */
    public function updateStatus(): void
    {
        // Don't update if already cancelled or completed
        if (in_array($this->status, ['cancelled', 'completed'])) {
            return;
        }

        $newStatus = $this->status;

        // Check if should be completed
        if ($this->produced_quantity >= $this->quantity) {
            $newStatus = 'completed';
        }
        // Check if should be in production (has approved artwork)
        elseif ($this->artworks()->where('is_approved', true)->exists()) {
            $newStatus = 'production';
        }
        // Check if should be in design (has designer assigned)
        elseif ($this->designer_id) {
            $newStatus = 'design';
        }
        // Default to draft
        else {
            $newStatus = 'draft';
        }

        if ($this->status !== $newStatus) {
            $this->update(['status' => $newStatus]);
        }
    }

    /**
     * Cancel the task
     */
    public function cancel(): void
    {
        $this->update(['status' => 'cancelled']);
    }
}

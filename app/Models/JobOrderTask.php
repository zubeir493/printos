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
            ->logOnly(['name', 'designer_id', 'typist_id', 'quantity', 'task_cost', 'status', 'size'])
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
        'typist_id',
        'name',
        'quantity',
        'task_cost',
        'paper',
        'deliverables',
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
            'typist_id' => 'integer',
            'task_cost' => 'decimal:2',
            'paper' => 'array',
            'deliverables' => 'array',
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

    public function typist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'typist_id');
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

    public function textFiles(): HasMany
    {
        return $this->hasMany(TextFile::class);
    }

    public function deliverableOptionsForType(string $type): array
    {
        return collect($this->deliverables ?? [])
            ->filter(fn ($deliverable): bool => is_array($deliverable)
                && ($deliverable['type'] ?? null) === $type
                && filled($deliverable['label'] ?? null))
            ->mapWithKeys(fn ($deliverable): array => [
                (string) $deliverable['label'] => (string) $deliverable['label'],
            ])
            ->all();
    }

    public function canStartProduction(): bool
    {
        return $this->canAutoStartProduction();
    }

    public function canAutoStartProduction(): bool
    {
        return $this->status === 'design' && $this->isReadyForProduction();
    }

    public function isReadyForProduction(): bool
    {
        $deliverables = array_values(array_filter((array) $this->deliverables ?? [], fn ($deliverable) => is_array($deliverable)));

        if ($deliverables === []) {
            return $this->artworks()->where('is_approved', true)->exists()
                || $this->textFiles()->where('is_approved', true)->exists();
        }

        foreach ($deliverables as $deliverable) {
            $type = $deliverable['type'] ?? null;
            $label = trim((string) ($deliverable['label'] ?? ''));

            if (! in_array($type, ['artwork', 'text_file'], true) || $label === '') {
                continue;
            }

            $approvedCount = match ($type) {
                'artwork' => $this->artworks()->where('is_approved', true)->where('deliverable', $label)->count(),
                'text_file' => $this->textFiles()->where('is_approved', true)->where('deliverable', $label)->count(),
            };

            if ($approvedCount < 1) {
                return false;
            }
        }

        return true;
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

        if ($this->produced_quantity >= $this->quantity && $this->isReadyForProduction()) {
            $newStatus = 'completed';
        } elseif ($this->status === 'production' && $this->produced_quantity > 0) {
            $newStatus = 'production';
        } elseif ($this->status === 'design' && $this->isReadyForProduction()) {
            $newStatus = 'production';
        } elseif ($this->status === 'design' && ! $this->designer_id && ! $this->typist_id) {
            $newStatus = 'draft';
        } elseif ($this->designer_id) {
            $newStatus = 'design';
        } elseif ($this->typist_id) {
            $newStatus = $this->status ?: 'draft';
        } else {
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

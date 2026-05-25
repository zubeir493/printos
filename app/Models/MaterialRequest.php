<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class MaterialRequest extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['requested_quantity', 'issued_quantity', 'reason'])
            ->logOnlyDirty()
            ->useLogName('material_request');
    }

    protected $fillable = [
        'job_order_task_id',
        'inventory_item_id',
        'required_quantity',
        'requested_quantity',
        'issued_quantity',
        'reason',
    ];

    protected static function booted(): void
    {
        static::saving(function ($model) {
            if ($model->required_quantity < 0) {
                throw new \InvalidArgumentException('Required quantity cannot be negative');
            }
            if ($model->requested_quantity < 0) {
                throw new \InvalidArgumentException('Requested quantity cannot be negative');
            }
            if ($model->issued_quantity < 0) {
                throw new \InvalidArgumentException('Issued quantity cannot be negative');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'job_order_task_id' => 'integer',
            'inventory_item_id' => 'integer',
            'required_quantity' => 'decimal:2',
            'requested_quantity' => 'decimal:2',
            'issued_quantity' => 'decimal:2',
        ];
    }

    public function jobOrderTask(): BelongsTo
    {
        return $this->belongsTo(JobOrderTask::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function issueApprovals(): HasMany
    {
        return $this->hasMany(MaterialIssueApproval::class);
    }

    public function pendingIssueApprovals(): HasMany
    {
        return $this->issueApprovals()->where('status', 'pending');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegacyJobOrderItem extends Model
{
    protected $fillable = [
        'legacy_job_order_group_id', 'job_order_id', 'legacy_task_id', 'task_order',
        'task_cost', 'original_quantity', 'task_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'legacy_job_order_group_id' => 'integer',
            'job_order_id' => 'integer',
            'legacy_task_id' => 'integer',
            'task_order' => 'integer',
            'task_cost' => 'decimal:2',
            'original_quantity' => 'integer',
            'task_snapshot' => 'array',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(LegacyJobOrderGroup::class, 'legacy_job_order_group_id');
    }

    public function jobOrder(): BelongsTo
    {
        return $this->belongsTo(JobOrder::class);
    }
}

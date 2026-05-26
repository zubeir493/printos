<?php

namespace App\Models;

use Database\Factories\CostEstimateTaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CostEstimateTask extends Model
{
    /** @use HasFactory<CostEstimateTaskFactory> */
    use HasFactory;

    protected $fillable = [
        'cost_estimate_id',
        'name',
        'quantity',
        'size',
        'unit_price',
        'task_cost',
        'paper',
        'deliverables',
        'instructions',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'cost_estimate_id' => 'integer',
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'task_cost' => 'decimal:2',
            'paper' => 'array',
            'deliverables' => 'array',
        ];
    }

    public function costEstimate(): BelongsTo
    {
        return $this->belongsTo(CostEstimate::class);
    }
}

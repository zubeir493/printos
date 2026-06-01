<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CostEstimateInput extends Model
{
    protected $fillable = [
        'cost_estimate_id',
        'step',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'cost_estimate_id' => 'integer',
            'payload' => 'array',
        ];
    }

    public function costEstimate(): BelongsTo
    {
        return $this->belongsTo(CostEstimate::class);
    }
}

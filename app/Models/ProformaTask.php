<?php

namespace App\Models;

use Database\Factories\ProformaTaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProformaTask extends Model
{
    /** @use HasFactory<ProformaTaskFactory> */
    use HasFactory;

    protected $fillable = [
        'proforma_id',
        'name',
        'quantity',
        'size',
        'unit_price',
        'task_cost',
        'paper',
        'deliverables',
        'instructions',
        'inputs',
        'cost_breakdown',
        'rate_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'proforma_id' => 'integer',
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'task_cost' => 'decimal:2',
            'paper' => 'array',
            'deliverables' => 'array',
            'inputs' => 'array',
            'cost_breakdown' => 'array',
            'rate_snapshot' => 'array',
        ];
    }

    public function proforma(): BelongsTo
    {
        return $this->belongsTo(Proforma::class);
    }
}

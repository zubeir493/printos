<?php

namespace App\Models;

use App\Support\SequentialNumber;
use Database\Factories\CostEstimateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CostEstimate extends Model
{
    /** @use HasFactory<CostEstimateFactory> */
    use HasFactory;

    protected $fillable = [
        'estimate_number',
        'job_type',
        'partner_id',
        'description',
        'quantity',
        'deadline',
        'services',
        'remarks',
        'subtotal',
        'overhead_amount',
        'profit_amount',
        'discount_amount',
        'vat_rate',
        'tax_amount',
        'total',
        'unit_price',
        'margin_percent',
        'formula_version',
        'settings_snapshot',
        'status',
        'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'partner_id' => 'integer',
            'quantity' => 'integer',
            'deadline' => 'date',
            'services' => 'array',
            'subtotal' => 'decimal:2',
            'overhead_amount' => 'decimal:2',
            'profit_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'unit_price' => 'decimal:4',
            'margin_percent' => 'decimal:2',
            'settings_snapshot' => 'array',
            'finalized_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CostEstimate $costEstimate): void {
            if (blank($costEstimate->estimate_number)) {
                $year = now()->format('Y');
                $costEstimate->estimate_number = SequentialNumber::next(
                    lockName: "cost_estimates:{$year}",
                    modelClass: self::class,
                    column: 'estimate_number',
                    prefix: "CE-{$year}-",
                    padding: 6,
                    likePattern: "CE-{$year}-%",
                );
            }
        });
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function inputs(): HasMany
    {
        return $this->hasMany(CostEstimateInput::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CostEstimateLine::class)->orderBy('sort');
    }

    public function proforma(): HasOne
    {
        return $this->hasOne(Proforma::class);
    }

    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }
}

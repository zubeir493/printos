<?php

namespace App\Models;

use App\Services\CostEstimates\CostEstimateCalculator;
use App\Support\SequentialNumber;
use Database\Factories\CostEstimateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CostEstimate extends Model
{
    /** @use HasFactory<CostEstimateFactory> */
    use HasFactory;

    protected $fillable = [
        'estimate_number',
        'job_type',
        'partner_id',
        'services',
        'remarks',
        'subtotal',
        'tax_amount',
        'total',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'partner_id' => 'integer',
            'services' => 'array',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CostEstimate $costEstimate): void {
            if (blank($costEstimate->estimate_number)) {
                $year = now()->format('Y');
                $costEstimate->estimate_number = SequentialNumber::next(
                    lockName: "cost-estimates:{$year}",
                    modelClass: self::class,
                    column: 'estimate_number',
                    prefix: "EST-{$year}-",
                    padding: 6,
                    likePattern: "EST-{$year}-%",
                );
            }
        });
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(CostEstimateTask::class);
    }

    public function proformas(): HasMany
    {
        return $this->hasMany(Proforma::class);
    }

    public function recalculateTotals(): void
    {
        $calculator = app(CostEstimateCalculator::class);
        $calculation = $calculator->calculate($this->job_type, $this->tasks()->get()->toArray());

        foreach ($calculation['tasks'] as $task) {
            if (! isset($task['id'])) {
                continue;
            }

            $this->tasks()
                ->whereKey($task['id'])
                ->update([
                    'quantity' => $task['quantity'],
                    'unit_price' => $task['unit_price'],
                    'task_cost' => $task['task_cost'],
                ]);
        }

        $this->updateQuietly([
            'subtotal' => $calculation['subtotal'],
            'tax_amount' => $calculation['tax_amount'],
            'total' => $calculation['total'],
        ]);
    }
}

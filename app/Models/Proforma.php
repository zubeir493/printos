<?php

namespace App\Models;

use App\Services\CostEstimates\CostEstimateCalculator;
use App\Support\Money;
use App\Support\SequentialNumber;
use Database\Factories\ProformaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Proforma extends Model
{
    /** @use HasFactory<ProformaFactory> */
    use HasFactory;

    protected $fillable = [
        'proforma_number',
        'cost_estimate_id',
        'partner_id',
        'job_type',
        'services',
        'issue_date',
        'expiry_date',
        'remarks',
        'subtotal',
        'tax_amount',
        'total',
        'status',
        'filename',
        'file_path',
        'emailed_at',
        'email_recipient',
        'approved_at',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'cost_estimate_id' => 'integer',
            'partner_id' => 'integer',
            'approved_by' => 'integer',
            'services' => 'array',
            'issue_date' => 'date',
            'expiry_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'emailed_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Proforma $proforma): void {
            if (blank($proforma->proforma_number)) {
                $year = now()->format('Y');
                $proforma->proforma_number = SequentialNumber::next(
                    lockName: "proformas:{$year}",
                    modelClass: self::class,
                    column: 'proforma_number',
                    prefix: "PF-{$year}-",
                    padding: 6,
                    likePattern: "PF-{$year}-%",
                );
            }
        });
    }

    public function costEstimate(): BelongsTo
    {
        return $this->belongsTo(CostEstimate::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProformaTask::class);
    }

    public function jobOrders(): HasMany
    {
        return $this->hasMany(JobOrder::class);
    }

    public function jobOrder(): HasOne
    {
        return $this->hasOne(JobOrder::class);
    }

    public function getFormattedTotalAttribute(): string
    {
        return Money::format($this->total);
    }

    public function canCreateJobOrder(): bool
    {
        return $this->status === 'approved' && ! $this->jobOrders()->exists();
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

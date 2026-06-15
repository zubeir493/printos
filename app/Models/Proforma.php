<?php

namespace App\Models;

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
            'partner_id' => 'integer',
            'cost_estimate_id' => 'integer',
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

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function costEstimate(): BelongsTo
    {
        return $this->belongsTo(CostEstimate::class);
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
        if ($this->status !== 'approved') {
            return false;
        }

        if ($this->relationLoaded('jobOrders')) {
            return $this->jobOrders->isEmpty();
        }

        if (array_key_exists('job_orders_exists', $this->attributes)) {
            return ! (bool) $this->attributes['job_orders_exists'];
        }

        return ! $this->jobOrders()->exists();
    }

    public function recalculateTotals(): void
    {
        $subtotal = 0;

        foreach ($this->tasks as $task) {
            $taskCost = round(max(1, (int) $task->quantity) * (float) $task->unit_price, 2);
            $task->updateQuietly(['task_cost' => $taskCost]);
            $subtotal += $taskCost;
        }

        $settings = Setting::getSettings();
        $taxRate = $settings->vat_enabled ? (float) $settings->vat_rate / 100 : 0.0;
        $taxAmount = round($subtotal * $taxRate, 2);

        $this->updateQuietly([
            'subtotal' => round($subtotal, 2),
            'tax_amount' => $taxAmount,
            'total' => round($subtotal + $taxAmount, 2),
        ]);
    }
}

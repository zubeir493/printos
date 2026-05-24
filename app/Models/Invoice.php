<?php

namespace App\Models;

use App\Support\Money;
use App\Support\SequentialNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Invoice extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['invoice_number', 'status', 'total_amount', 'balance_due', 'emailed_at', 'partner_id', 'due_date'])
            ->logOnlyDirty()
            ->useLogName('invoice');
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'invoice_number',
        'invoice_type', // sales, purchase, service, receipt
        'order_id',
        'order_type', // sales_order, purchase_order, job_order, payment
        'partner_id',
        'invoice_date',
        'due_date',
        'subtotal',
        'tax_amount',
        'total_amount',
        'balance_due',
        'status', // draft, sent, paid, overdue, cancelled
        'filename',
        'file_path',
        'emailed_at',
        'email_recipient',
        'tax_calculations', // JSON
        'options', // JSON
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'order_id' => 'integer',
            'partner_id' => 'integer',
            'invoice_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'balance_due' => 'decimal:2',
            'emailed_at' => 'datetime',
            'tax_calculations' => 'array',
            'options' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function ($invoice): void {
            if (empty($invoice->invoice_number)) {
                $prefix = match ($invoice->invoice_type) {
                    'purchase' => 'PI-',
                    'service' => 'SI-',
                    'receipt' => 'RCP-',
                    default => 'INV-',
                };

                $invoice->invoice_number = SequentialNumber::next(
                    lockName: 'invoices:'.$prefix,
                    modelClass: self::class,
                    column: 'invoice_number',
                    prefix: $prefix,
                    padding: 6,
                    likePattern: $prefix.'%',
                );
            }

            if (is_null($invoice->balance_due)) {
                $invoice->balance_due = $invoice->total_amount ?? 0;
            }
        });
    }

    /**
     * Get the partner that owns the invoice.
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /**
     * Explicit relationships for manual eager loading.
     */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'order_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'order_id');
    }

    public function jobOrder(): BelongsTo
    {
        return $this->belongsTo(JobOrder::class, 'order_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'order_id');
    }

    /**
     * Get the related order based on order_type.
     */
    public function order()
    {
        return match ($this->order_type) {
            'sales_order' => $this->salesOrder(),
            'purchase_order' => $this->purchaseOrder(),
            'job_order' => $this->jobOrder(),
            'payment' => $this->payment(),
            default => null,
        };
    }

    /**
     * Scope to get invoices by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('invoice_type', $type);
    }

    /**
     * Scope to get invoices by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get overdue invoices.
     */
    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
            ->where('status', '!=', 'paid');
    }

    /**
     * Check if invoice is paid.
     */
    public function isPaid(): bool
    {
        return $this->balance_due <= 0;
    }

    /**
     * Check if invoice is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->due_date->isPast() && ! $this->isPaid();
    }

    /**
     * Get formatted total amount.
     */
    public function getFormattedTotalAttribute(): string
    {
        return Money::format($this->total_amount);
    }

    /**
     * Get formatted balance due.
     */
    public function getFormattedBalanceAttribute(): string
    {
        return Money::format($this->balance_due);
    }
}

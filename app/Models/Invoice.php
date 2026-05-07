<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Invoice extends Model
{
    use HasFactory;

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

                // Wrap in a transaction so lockForUpdate is effective.
                DB::transaction(function () use ($invoice, $prefix): void {
                    $last = self::lockForUpdate()->orderBy('id', 'desc')->first();
                    $nextId = $last ? $last->id + 1 : 1;
                    $invoice->invoice_number = $prefix.str_pad($nextId, 6, '0', STR_PAD_LEFT);
                });
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
     * Get the related order based on order_type.
     */
    public function order()
    {
        return match ($this->order_type) {
            'sales_order' => $this->belongsTo(SalesOrder::class, 'order_id'),
            'purchase_order' => $this->belongsTo(PurchaseOrder::class, 'order_id'),
            'job_order' => $this->belongsTo(JobOrder::class, 'order_id'),
            'payment' => $this->belongsTo(Payment::class, 'order_id'),
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
        return number_format($this->total_amount, 2).' ETB';
    }

    /**
     * Get formatted balance due.
     */
    public function getFormattedBalanceAttribute(): string
    {
        return number_format($this->balance_due, 2).' ETB';
    }
}

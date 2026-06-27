<?php

namespace App\Models;

use App\Support\SequentialNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Payment extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->useLogName('finance');
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'payment_number',
        'partner_id',
        'bank_id',
        'payment_date',
        'amount',
        'withholding_amount',
        'direction',
        'method',
        'reference',
        'payable_type',
        'payable_id',
        'transaction_type',
        'payment_type',
        'account_id',
        'expense_account_id',
        'petty_cash_account_id',
        'voided_at',
        'voided_by',
        'void_reason',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'partner_id' => 'integer',
            'bank_id' => 'integer',
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'withholding_amount' => 'decimal:2',
            'account_id' => 'integer',
            'payable_id' => 'integer',
            'expense_account_id' => 'integer',
            'petty_cash_account_id' => 'integer',
            'voided_at' => 'datetime',
            'voided_by' => 'integer',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function journalEntries(): MorphMany
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function setCustomerPartnerIdAttribute($value)
    {
        $this->attributes['partner_id'] = $value;
    }

    public function setSupplierPartnerIdAttribute($value)
    {
        $this->attributes['partner_id'] = $value;
    }

    protected static function booted(): void
    {
        static::creating(function (self $payment): void {
            if (! empty($payment->payment_number)) {
                return;
            }

            $payment->payment_number = SequentialNumber::next(
                lockName: 'payments',
                modelClass: self::class,
                column: 'payment_number',
                prefix: 'PAY-',
                padding: 6,
                likePattern: 'PAY-%',
            );
        });

        static::saved(function (self $payment): void {
            $payment->syncRelatedDocumentPaymentState();
        });

        static::deleted(function (self $payment): void {
            $payment->syncRelatedDocumentPaymentState();
        });
    }

    public function syncRelatedDocumentPaymentState(): void
    {
        $payable = $this->payable;

        if ($payable instanceof JobOrder) {
            $jobOrder = $payable->refresh();
            $jobOrder->updateQuietly([
                'advance_paid' => $jobOrder->paid_amount > 0,
                'advance_amount' => $jobOrder->paid_amount,
            ]);
            $jobOrder->syncCompletionStatus();
            $this->syncInvoicesForDocument($jobOrder);
        }

        if ($payable instanceof SalesOrder) {
            $salesOrder = $payable->refresh();

            if (
                $salesOrder->status === SalesOrder::STATUS_SUBMITTED &&
                $salesOrder->isPaidInFull()
            ) {
                $salesOrder->updateQuietly(['status' => SalesOrder::STATUS_COMPLETED]);
            }

            $this->syncInvoicesForDocument($salesOrder);
        }

        if ($payable instanceof PurchaseOrder) {
            $this->syncInvoicesForDocument($payable->refresh());
        }

        if ($payable instanceof Invoice) {
            $this->syncInvoiceBalance($payable->refresh());
        }
    }

    private function syncInvoicesForDocument(SalesOrder|JobOrder|PurchaseOrder $document): void
    {
        $paidAmount = (float) $document->payments()
            ->whereNull('voided_at')
            ->sum('amount');

        $document->invoices()->get()->each(function (Invoice $invoice) use ($document, $paidAmount): void {
            $this->syncInvoiceBalance($invoice, (float) $document->total, $paidAmount);
        });
    }

    private function syncInvoiceBalance(Invoice $invoice, ?float $totalAmount = null, ?float $paidAmount = null): void
    {
        if ($invoice->status === 'cancelled') {
            return;
        }

        $totalAmount ??= (float) $invoice->total_amount;
        $paidAmount ??= (float) $invoice->payments()
            ->whereNull('voided_at')
            ->sum('amount');

        $balanceDue = max(0, round($totalAmount - $paidAmount, 2));

        $status = $invoice->status;
        if ($balanceDue <= 0.001) {
            $status = 'paid';
        } elseif ($paidAmount > 0) {
            $status = 'partial';
        } elseif ($status === 'paid' || $status === 'partial') {
            $status = 'sent';
        }

        $invoice->updateQuietly([
            'balance_due' => $balanceDue,
            'status' => $status,
        ]);
    }
}

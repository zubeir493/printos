<?php

namespace App\Models;

use App\Enums\ExpenseTrackingType;
use App\Enums\PaymentTransactionType;
use App\Notifications\InvoiceStatusChangedNotification;
use App\Notifications\JobOrderCompletedNotification;
use App\Support\NotificationRecipients;
use App\Support\SequentialNumber;
use App\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Notification;
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
        'expense_tracking_type',
        'expense_tracking_item_id',
        'expense_tracking_employee_id',
        'expense_tracking_bid_id',
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
            'expense_tracking_item_id' => 'integer',
            'expense_tracking_employee_id' => 'integer',
            'expense_tracking_bid_id' => 'integer',
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

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
    }

    public function pettyCashAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'petty_cash_account_id');
    }

    public function expenseTrackingItem(): BelongsTo
    {
        return $this->belongsTo(ExpenseTrackingItem::class);
    }

    public function expenseTrackingEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'expense_tracking_employee_id');
    }

    public function expenseTrackingBid(): BelongsTo
    {
        return $this->belongsTo(Bid::class, 'expense_tracking_bid_id');
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
            if ($jobOrder->syncCompletionStatus()) {
                $recipients = NotificationRecipients::roles(UserRole::Sales, UserRole::Finance, UserRole::Operations);

                if ($recipients->isNotEmpty()) {
                    Notification::send($recipients, new JobOrderCompletedNotification($jobOrder->refresh()));
                }
            }
            $this->syncInvoicesForDocument($jobOrder);
        }

        if ($payable instanceof SalesOrder) {
            $salesOrder = $payable->refresh();

            if (
                $salesOrder->status === SalesOrder::STATUS_SUBMITTED &&
                $salesOrder->isPaidInFull()
            ) {
                $salesOrder->updateQuietly(['status' => SalesOrder::STATUS_COMPLETED]);
            } elseif (
                $salesOrder->payment_mode === 'credit' &&
                in_array($salesOrder->status, [SalesOrder::STATUS_DRAFT, SalesOrder::STATUS_DEPOSIT_RECEIVED], true)
            ) {
                $salesOrder->updateQuietly([
                    'status' => $salesOrder->paid_amount > 0
                        ? SalesOrder::STATUS_DEPOSIT_RECEIVED
                        : SalesOrder::STATUS_DRAFT,
                ]);
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

    public function expenseTrackingLabel(): ?string
    {
        $trackingType = ExpenseTrackingType::tryFrom((string) $this->expense_tracking_type);

        if (! $trackingType || $trackingType === ExpenseTrackingType::NONE) {
            return null;
        }

        $value = match (true) {
            $trackingType->usesTrackingItem() => $this->expenseTrackingItem?->display_name,
            $trackingType->usesEmployee() => $this->expenseTrackingEmployee?->full_name,
            $trackingType->usesBid() => $this->expenseTrackingBid?->bid_number,
            default => null,
        };

        return $value ? "{$trackingType->label()}: {$value}" : $trackingType->label();
    }

    public function paymentSource(): string
    {
        if ($this->transaction_type === PaymentTransactionType::PETTY_CASH_EXPENSE->value) {
            return 'petty_cash';
        }

        return match ($this->method) {
            'bank_transfer' => 'bank',
            'check' => 'cheque',
            default => $this->method ?? 'cash',
        };
    }

    public function paymentSourceLabel(): string
    {
        return match ($this->paymentSource()) {
            'petty_cash' => 'Petty Cash',
            'bank' => 'Bank Transfer',
            'cheque' => 'Cheque',
            'cpo' => 'CPO',
            default => 'Cash',
        };
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

        $previousStatus = $invoice->status;
        $status = $previousStatus;
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

        if ($previousStatus !== $status && in_array($status, ['sent', 'paid'], true)) {
            $recipients = NotificationRecipients::roles(UserRole::Admin, UserRole::Finance, UserRole::Sales, UserRole::Operations);

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new InvoiceStatusChangedNotification($invoice->refresh()));
            }
        }
    }
}

<?php

namespace App\Models;

use App\Enums\ExpenseTrackingType;
use App\Enums\PaymentTransactionType;
use App\Notifications\InvoiceStatusChangedNotification;
use App\Support\NotificationRecipients;
use App\Support\SequentialNumber;
use App\UserRole;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Payment extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    public function save(array $options = []): bool
    {
        return DB::transaction(fn (): bool => parent::save($options));
    }

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

    public function loanInstallments(): BelongsToMany
    {
        return $this->belongsToMany(EmployeeLoanInstallment::class)->withPivot('amount');
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeEffectiveOn(Builder $query, string $asOfDate): Builder
    {
        $afterDate = CarbonImmutable::parse($asOfDate)->startOfDay()->addDay();

        return $query->whereDate('payment_date', '<=', $asOfDate)
            ->where(fn (Builder $query) => $query->whereNull('voided_at')->orWhere('voided_at', '>=', $afterDate));
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
            $allocatedJobOrderIds = DB::table('legacy_job_order_payment_allocations')
                ->where('payment_id', $this->id)->pluck('job_order_id')->all();
            $jobOrders = $allocatedJobOrderIds === []
                ? collect([$payable])
                : JobOrder::query()->whereIn('id', $allocatedJobOrderIds)->get();

            foreach ($jobOrders as $jobOrder) {
                $jobOrder->refresh();
                $advance = ['advance_paid' => $jobOrder->paid_amount > 0];
                if (! $jobOrder->legacy_job_order_group_id) {
                    $advance['advance_amount'] = $jobOrder->paid_amount;
                }
                $jobOrder->updateQuietly($advance);
                $jobOrder->syncCompletionStatus();
            }

            $jobOrder = $payable->refresh();
            $this->syncInvoicesForDocument($jobOrder);
        }

        if ($payable instanceof SalesOrder) {
            $salesOrder = $payable->refresh();

            if (
                $salesOrder->status === SalesOrder::STATUS_SUBMITTED &&
                $salesOrder->isPaidInFull()
            ) {
                $salesOrder->updateQuietly(['status' => SalesOrder::STATUS_COMPLETED]);
            } elseif ($salesOrder->status === SalesOrder::STATUS_COMPLETED && ! $salesOrder->isPaidInFull()) {
                $salesOrder->updateQuietly(['status' => SalesOrder::STATUS_SUBMITTED]);
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
            DB::transaction(function () use ($payable): void {
                $invoice = Invoice::query()->lockForUpdate()->findOrFail($payable->id);
                $paidAmount = (float) $invoice->payments()
                    ->where('direction', $invoice->invoice_type === 'purchase' ? 'outbound' : 'inbound')
                    ->whereNull('voided_at')
                    ->sum('amount');
                $this->syncInvoiceBalance($invoice, $paidAmount);
            });
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
        DB::transaction(function () use ($document): void {
            $invoices = $document->invoices()->orderBy('id')->lockForUpdate()->get();
            if ($invoices->isEmpty()) {
                return;
            }

            $paidAmount = (float) $document->payments()
                ->where('direction', $document instanceof PurchaseOrder ? 'outbound' : 'inbound')
                ->whereNull('voided_at')
                ->sum('amount');

            foreach ($invoices as $invoice) {
                $this->syncInvoiceBalance($invoice, $paidAmount);
            }
        });
    }

    private function syncInvoiceBalance(Invoice $invoice, float $paidAmount): void
    {
        $previousStatus = $invoice->status;
        $invoice->syncPaymentBalance($paidAmount);

        if ($previousStatus !== $invoice->status && in_array($invoice->status, ['sent', 'paid'], true)) {
            $recipients = NotificationRecipients::roles(UserRole::Admin, UserRole::Finance, UserRole::Sales, UserRole::Operations);

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new InvoiceStatusChangedNotification($invoice->refresh()));
            }
        }
    }
}

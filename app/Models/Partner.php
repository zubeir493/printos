<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Partner extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->useLogName('partner');
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
        'tin_number',
        'is_supplier',
        'is_customer',
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
            'is_supplier' => 'boolean',
            'is_customer' => 'boolean',
        ];
    }

    public function jobOrders(): HasMany
    {
        return $this->hasMany(JobOrder::class);
    }

    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class);
    }

    public function issuedBidBonds(): HasMany
    {
        return $this->hasMany(Bond::class, 'issuing_partner_id')->where('type', Bond::TYPE_BID);
    }

    public function bonds(): HasMany
    {
        return $this->hasMany(Bond::class, 'issuing_partner_id');
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function salesInvoices(): HasMany
    {
        return $this->hasMany(SalesInvoice::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function purchaseInvoices(): HasMany
    {
        return $this->hasMany(PurchaseInvoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return array{
     *     receivable_total: float,
     *     payable_total: float,
     *     inbound_payments_total: float,
     *     outbound_payments_total: float,
     *     net_balance: float
     * }
     */
    public function statementSummary(): array
    {
        $receivableTotal = $this->receivableBalance();
        $payableTotal = $this->payableBalance();
        $inboundPaymentsTotal = $this->paymentTotal('inbound');
        $outboundPaymentsTotal = $this->paymentTotal('outbound');

        return [
            'receivable_total' => $receivableTotal,
            'payable_total' => $payableTotal,
            'inbound_payments_total' => $inboundPaymentsTotal,
            'outbound_payments_total' => $outboundPaymentsTotal,
            'net_balance' => $receivableTotal - $payableTotal,
        ];
    }

    public function receivableBalance(): float
    {
        $salesOrderBalance = $this->salesOrders()
            ->whereIn('status', [
                SalesOrder::STATUS_DEPOSIT_RECEIVED,
                SalesOrder::STATUS_SUBMITTED,
                SalesOrder::STATUS_COMPLETED,
            ])
            ->with(['payments' => fn ($query) => $query->whereNull('voided_at')])
            ->get()
            ->sum(fn (SalesOrder $salesOrder): float => $this->statementDocumentBalance($salesOrder));

        $jobOrderBalance = $this->jobOrders()
            ->whereIn('status', ['active', 'completed'])
            ->with(['payments' => fn ($query) => $query->whereNull('voided_at')])
            ->get()
            ->sum(fn (JobOrder $jobOrder): float => $this->statementDocumentBalance($jobOrder));

        $standaloneInvoiceBalance = $this->standaloneStatementInvoices(['sales', 'service'])
            ->sum(fn (Invoice $invoice): float => $this->statementInvoiceBalance($invoice));

        return round($salesOrderBalance + $jobOrderBalance + $standaloneInvoiceBalance, 2);
    }

    public function payableBalance(): float
    {
        $purchaseOrderBalance = $this->purchaseOrders()
            ->whereIn('status', ['approved', 'received'])
            ->with(['payments' => fn ($query) => $query->whereNull('voided_at')])
            ->get()
            ->sum(fn (PurchaseOrder $purchaseOrder): float => $this->statementDocumentBalance($purchaseOrder));

        $standaloneInvoiceBalance = $this->standaloneStatementInvoices(['purchase'])
            ->sum(fn (Invoice $invoice): float => $this->statementInvoiceBalance($invoice));

        return round($purchaseOrderBalance + $standaloneInvoiceBalance, 2);
    }

    public function paymentTotal(string $direction): float
    {
        return round((float) $this->payments()
            ->where('direction', $direction)
            ->whereNull('voided_at')
            ->sum('amount'), 2);
    }

    /**
     * @return Collection<int, array{
     *     date: CarbonInterface|null,
     *     type: string,
     *     reference: string,
     *     description: string,
     *     total: float,
     *     paid: float,
     *     open_balance: float,
     *     balance_impact: float,
     *     status: string
     * }>
     */
    public function statementRows(): Collection
    {
        $salesOrderRows = $this->salesOrders()
            ->whereIn('status', [
                SalesOrder::STATUS_DEPOSIT_RECEIVED,
                SalesOrder::STATUS_SUBMITTED,
                SalesOrder::STATUS_COMPLETED,
            ])
            ->with(['payments' => fn ($query) => $query->whereNull('voided_at')])
            ->latest('order_date')
            ->get()
            ->map(fn (SalesOrder $salesOrder): array => $this->documentStatementRow(
                date: $salesOrder->order_date,
                category: 'receivable',
                type: 'Sales Order',
                reference: $salesOrder->order_number,
                description: 'Customer sale',
                total: (float) $salesOrder->total,
                paid: $this->statementDocumentPaidAmount($salesOrder),
                status: $salesOrder->status,
                documentKey: 'sales-order-'.$salesOrder->id,
            ));

        $jobOrderRows = $this->jobOrders()
            ->whereIn('status', ['active', 'completed'])
            ->with(['payments' => fn ($query) => $query->whereNull('voided_at')])
            ->latest('submission_date')
            ->get()
            ->map(fn (JobOrder $jobOrder): array => $this->documentStatementRow(
                date: $jobOrder->submission_date,
                category: 'receivable',
                type: 'Job Order',
                reference: $jobOrder->job_order_number,
                description: 'Production job',
                total: (float) $jobOrder->total,
                paid: $this->statementDocumentPaidAmount($jobOrder),
                status: (string) $jobOrder->status,
                documentKey: 'job-order-'.$jobOrder->id,
            ));

        $purchaseOrderRows = $this->purchaseOrders()
            ->whereIn('status', ['approved', 'received'])
            ->with(['payments' => fn ($query) => $query->whereNull('voided_at')])
            ->latest('order_date')
            ->get()
            ->map(fn (PurchaseOrder $purchaseOrder): array => $this->documentStatementRow(
                date: $purchaseOrder->order_date,
                category: 'payable',
                type: 'Purchase Order',
                reference: $purchaseOrder->po_number,
                description: 'Supplier purchase',
                total: (float) $purchaseOrder->total,
                paid: $this->statementDocumentPaidAmount($purchaseOrder),
                status: $purchaseOrder->status,
                documentKey: 'purchase-order-'.$purchaseOrder->id,
            ));

        $invoiceRows = $this->invoices()
            ->latest('invoice_date')
            ->get()
            ->map(function (Invoice $invoice): array {
                $isReceivable = in_array($invoice->invoice_type, ['sales', 'service'], true);
                $total = (float) $invoice->total_amount;
                $isStandalone = $this->isStandaloneStatementInvoice($invoice);
                $balance = $isStandalone ? $this->statementInvoiceBalance($invoice) : 0.0;

                return [
                    '__key' => 'invoice-'.$invoice->id,
                    'date' => $invoice->invoice_date,
                    'category' => $isReceivable ? 'receivable' : 'payable',
                    'type' => match ($invoice->invoice_type) {
                        'purchase' => 'Purchase Invoice',
                        'service' => 'Service Invoice',
                        'receipt' => 'Receipt',
                        default => 'Sales Invoice',
                    },
                    'reference' => $invoice->invoice_number,
                    'description' => $isStandalone
                        ? ($balance > 0 ? 'Standalone invoice balance' : 'Standalone invoice settled')
                        : 'Invoice issued for related document',
                    'total' => $total,
                    'paid' => $isStandalone ? max(0.0, $total - $balance) : 0.0,
                    'open_balance' => $balance,
                    'balance_impact' => $isReceivable ? $balance : -1 * $balance,
                    'status' => $invoice->status,
                ];
            });

        $paymentRows = $this->payments()
            ->with('payable')
            ->latest('payment_date')
            ->get()
            ->map(function (Payment $payment): array {
                $amount = (float) $payment->amount;
                $isInbound = $payment->direction === 'inbound';
                $isVoided = filled($payment->voided_at);

                return [
                    '__key' => 'payment-'.$payment->id,
                    'date' => $payment->payment_date,
                    'category' => 'payment',
                    'type' => $isInbound ? 'Payment Received' : 'Payment Sent',
                    'reference' => $payment->payment_number,
                    'description' => collect([
                        $payment->method,
                        $payment->reference,
                        $payment->payable ? 'Applied to '.$this->statementPayableReference($payment->payable) : null,
                    ])->filter()->implode(' / '),
                    'total' => $amount,
                    'paid' => $isVoided ? 0.0 : $amount,
                    'open_balance' => 0.0,
                    'balance_impact' => $isVoided ? 0.0 : ($isInbound ? -1 * $amount : $amount),
                    'status' => $isVoided ? 'voided' : 'posted',
                ];
            });

        return $invoiceRows
            ->concat($salesOrderRows)
            ->concat($jobOrderRows)
            ->concat($purchaseOrderRows)
            ->concat($paymentRows)
            ->sortByDesc(fn (array $row): string => ($row['date']?->toDateString() ?? '0000-00-00').'|'.$row['reference'])
            ->values();
    }

    private function statementInvoiceBalance(Invoice $invoice): float
    {
        if (in_array($invoice->status, ['cancelled', 'paid'], true)) {
            return 0.0;
        }

        return max(0.0, round((float) $invoice->balance_due, 2));
    }

    private function statementDocumentBalance(SalesOrder|JobOrder|PurchaseOrder $document): float
    {
        return max(0.0, round((float) $document->total - $this->statementDocumentPaidAmount($document), 2));
    }

    private function statementDocumentPaidAmount(SalesOrder|JobOrder|PurchaseOrder $document): float
    {
        if ($document->relationLoaded('payments')) {
            return round((float) $document->payments->whereNull('voided_at')->sum('amount'), 2);
        }

        return round((float) $document->payments()->whereNull('voided_at')->sum('amount'), 2);
    }

    private function documentStatementRow(
        CarbonInterface|string|null $date,
        string $category,
        string $type,
        string $reference,
        string $description,
        float $total,
        float $paid,
        string $status,
        string $documentKey,
    ): array {
        $openBalance = max(0.0, round($total - $paid, 2));

        return [
            '__key' => $documentKey,
            'date' => $date,
            'category' => $category,
            'type' => $type,
            'reference' => $reference,
            'description' => $description,
            'total' => $total,
            'paid' => $paid,
            'open_balance' => $openBalance,
            'balance_impact' => $category === 'payable' ? -1 * $openBalance : $openBalance,
            'status' => $status,
        ];
    }

    /**
     * @param  array<int, string>  $invoiceTypes
     * @return Collection<int, Invoice>
     */
    private function standaloneStatementInvoices(array $invoiceTypes): Collection
    {
        return $this->invoices()
            ->whereIn('invoice_type', $invoiceTypes)
            ->whereNotIn('status', ['cancelled', 'paid'])
            ->get()
            ->filter(fn (Invoice $invoice): bool => $this->isStandaloneStatementInvoice($invoice));
    }

    private function isStandaloneStatementInvoice(Invoice $invoice): bool
    {
        return ! in_array($invoice->order_type, ['sales_order', 'job_order', 'purchase_order'], true)
            || blank($invoice->order_id);
    }

    private function statementPayableReference(Model $payable): string
    {
        return match (true) {
            $payable instanceof SalesOrder => $payable->order_number,
            $payable instanceof JobOrder => $payable->job_order_number,
            $payable instanceof PurchaseOrder => $payable->po_number,
            $payable instanceof Invoice => $payable->invoice_number,
            default => class_basename($payable).' #'.$payable->getKey(),
        };
    }
}

<?php

namespace App\Console\Commands;

use App\Enums\PaymentTransactionType;
use App\Models\Account;
use App\Models\JobOrder;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Services\Accounting\CreatePaymentJournalEntry;
use App\Services\Accounting\CreatePurchaseOrderJournalEntry;
use App\Services\Accounting\CreateSalesJournalEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairFinanceJournals extends Command
{
    protected $signature = 'finance:repair-journals {--dry-run : Report missing or misclassified entries without changing data}';

    protected $description = 'Backfill missing finance journal entries and correct AP account usage';

    public function handle(
        CreatePaymentJournalEntry $paymentJournalEntry,
        CreatePurchaseOrderJournalEntry $purchaseOrderJournalEntry,
        CreateSalesJournalEntry $salesJournalEntry,
    ): int {
        $dryRun = (bool) $this->option('dry-run');

        $counts = [
            'payments' => 0,
            'sales_orders' => 0,
            'job_orders' => 0,
            'purchase_orders' => 0,
            'account_lines' => 0,
        ];

        DB::transaction(function () use ($dryRun, $paymentJournalEntry, $purchaseOrderJournalEntry, $salesJournalEntry, &$counts): void {
            Payment::query()
                ->whereNull('voided_at')
                ->where(function ($query): void {
                    $query->whereNull('transaction_type')
                        ->orWhere('transaction_type', '!=', PaymentTransactionType::CASH_SALE_RECEIPT->value);
                })
                ->whereDoesntHave('journalEntries', fn ($query) => $query->whereNull('reversal_of_journal_entry_id'))
                ->orderBy('id')
                ->get()
                ->each(function (Payment $payment) use ($dryRun, $paymentJournalEntry, &$counts): void {
                    $counts['payments']++;

                    if (! $dryRun) {
                        $paymentJournalEntry->handle($payment);
                    }
                });

            SalesOrder::query()
                ->whereIn('status', [SalesOrder::STATUS_SUBMITTED, SalesOrder::STATUS_COMPLETED])
                ->whereNotExists($this->journalExistsSubquery(SalesOrder::class, 'sales_orders.id'))
                ->orderBy('id')
                ->get()
                ->each(function (SalesOrder $salesOrder) use ($dryRun, $salesJournalEntry, &$counts): void {
                    $counts['sales_orders']++;

                    if (! $dryRun) {
                        $salesJournalEntry->handle($salesOrder);
                    }
                });

            JobOrder::query()
                ->where('status', 'completed')
                ->whereNotExists($this->journalExistsSubquery(JobOrder::class, 'job_orders.id'))
                ->orderBy('id')
                ->get()
                ->each(function (JobOrder $jobOrder) use ($dryRun, $salesJournalEntry, &$counts): void {
                    $counts['job_orders']++;

                    if (! $dryRun) {
                        $salesJournalEntry->handle($jobOrder);
                    }
                });

            PurchaseOrder::query()
                ->where('status', 'received')
                ->whereNotExists($this->journalExistsSubquery(PurchaseOrder::class, 'purchase_orders.id'))
                ->orderBy('id')
                ->get()
                ->each(function (PurchaseOrder $purchaseOrder) use ($dryRun, $purchaseOrderJournalEntry, &$counts): void {
                    $counts['purchase_orders']++;

                    if (! $dryRun) {
                        $purchaseOrderJournalEntry->handle($purchaseOrder);
                    }
                });

            $counts['account_lines'] = $this->repairAccountsPayableLines($dryRun);
        });

        foreach ($counts as $label => $count) {
            $this->line(str($label)->replace('_', ' ')->headline().": {$count}");
        }

        $this->info($dryRun ? 'Dry run complete.' : 'Finance journal repair complete.');

        return self::SUCCESS;
    }

    private function journalExistsSubquery(string $sourceType, string $sourceIdColumn): \Closure
    {
        return function ($query) use ($sourceType, $sourceIdColumn): void {
            $query->selectRaw('1')
                ->from('journal_entries')
                ->where('journal_entries.source_type', $sourceType)
                ->whereNull('journal_entries.reversal_of_journal_entry_id')
                ->whereColumn('journal_entries.source_id', $sourceIdColumn);
        };
    }

    private function repairAccountsPayableLines(bool $dryRun): int
    {
        $accountsPayable = $dryRun
            ? Account::query()->where('code', Account::CODE_AP)->first()
            : Account::getSystemAccount(Account::CODE_AP, 'Accounts Payable', 'Liability');
        $vatPayable = $dryRun
            ? Account::query()->where('code', '2100')->first()
            : Account::getSystemAccount('2100', 'VAT Payable', 'Liability');

        if (! $accountsPayable || ! $vatPayable) {
            return 0;
        }

        $purchaseJournalIds = JournalEntry::query()
            ->where('source_type', PurchaseOrder::class)
            ->pluck('id');

        $supplierPaymentJournalIds = JournalEntry::query()
            ->join('payments', 'payments.id', '=', 'journal_entries.source_id')
            ->where('journal_entries.source_type', Payment::class)
            ->where('payments.transaction_type', PaymentTransactionType::SUPPLIER_PAYMENT->value)
            ->pluck('journal_entries.id');

        $query = JournalItem::query()
            ->where('account_id', $vatPayable->id)
            ->where(function ($query) use ($purchaseJournalIds, $supplierPaymentJournalIds): void {
                $query->where(function ($query) use ($purchaseJournalIds): void {
                    $query->whereIn('journal_entry_id', $purchaseJournalIds)
                        ->where('credit', '>', 0);
                })->orWhere(function ($query) use ($supplierPaymentJournalIds): void {
                    $query->whereIn('journal_entry_id', $supplierPaymentJournalIds)
                        ->where('debit', '>', 0);
                });
            });

        $count = $query->count();

        if (! $dryRun && $count > 0) {
            $query->update(['account_id' => $accountsPayable->id]);
        }

        return $count;
    }
}

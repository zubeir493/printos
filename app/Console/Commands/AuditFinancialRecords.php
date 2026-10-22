<?php

namespace App\Console\Commands;

use App\Enums\PaymentTransactionType;
use App\Models\Account;
use App\Models\AccountingExport;
use App\Models\Bank;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Proforma;
use App\Support\PrivateStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AuditFinancialRecords extends Command
{
    protected $signature = 'finance:audit-records {--check-files : Check that referenced generated files exist} {--limit=100 : Maximum findings to display per category}';

    protected $description = 'Report financial reconciliation candidates without changing records';

    public function handle(): int
    {
        $limit = max(1, min(10000, (int) $this->option('limit')));
        $cpo = Payment::query()->where('method', 'cpo')->whereNull('voided_at')
            ->where(fn ($query) => $query->whereNull('bank_id')->orWhereHas('journalEntries.journalItems.account', fn ($accounts) => $accounts->where('code', Account::CODE_CASH)))
            ->limit($limit)->pluck('payment_number');
        $unposted = Payment::query()->whereNull('voided_at')->where('amount', '>', 0)
            ->where(fn ($query) => $query->whereNull('transaction_type')->orWhere('transaction_type', '!=', PaymentTransactionType::CASH_SALE_RECEIPT->value))
            ->whereDoesntHave('journalEntries')->limit($limit)->pluck('payment_number');
        $this->line('CPO reconciliation candidates: '.$cpo->count());
        foreach ($cpo as $number) {
            $this->line('  '.$number);
        }
        $this->line('Payments without journals: '.$unposted->count());
        foreach ($unposted as $number) {
            $this->line('  '.$number);
        }
        $bankDifferences = 0;
        foreach (Bank::query()->cursor() as $bank) {
            $expected = round((float) $bank->opening_balance + (float) DB::table('bank_transactions')->where('bank_id', $bank->id)->sum('balance_delta'), 2);
            if (abs($expected - (float) $bank->current_balance) > 0.009) {
                $bankDifferences++;
                if ($bankDifferences <= $limit) {
                    $this->line('Bank balance reconciliation candidate: '.$bank->id);
                }
            }
        }
        $this->line('Bank balance differences: '.$bankDifferences);
        $missingFiles = 0;
        $unavailable = 0;
        if ($this->option('check-files')) {
            foreach ([Invoice::class => PrivateStorage::diskName(), Proforma::class => 'local', AccountingExport::class => PrivateStorage::diskName()] as $model => $disk) {
                foreach ($model::query()->whereNotNull('file_path')->lazyById() as $record) {
                    try {
                        if (! Storage::disk($disk)->exists($record->file_path)) {
                            $missingFiles++;
                            if ($missingFiles <= $limit) {
                                $this->line('Missing generated file: '.class_basename($model).' #'.$record->id);
                            }
                        }
                    } catch (Throwable $exception) {
                        $unavailable++;
                        report($exception);
                    }
                }
            }
            $this->line('Missing generated files: '.$missingFiles);
            $this->line('File checks unavailable: '.$unavailable);
        }
        $this->info('Read-only audit complete. No financial records were changed.');

        return $unavailable > 0 ? self::FAILURE : self::SUCCESS;
    }
}

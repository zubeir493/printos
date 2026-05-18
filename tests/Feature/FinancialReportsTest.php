<?php

use App\Filament\Finance\Pages\AccountStatementReport;
use App\Filament\Finance\Pages\PayablesAgingReport;
use App\Filament\Finance\Pages\ProfitLossStatementReport;
use App\Filament\Finance\Pages\TrialBalanceReport;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('trial balance keeps zero balance accounts when date filters are applied', function (): void {
    $cash = Account::create([
        'code' => '1010',
        'name' => 'Cash',
        'type' => 'Asset',
    ]);

    $entry = JournalEntry::create([
        'date' => '2025-12-31',
        'reference' => 'OLD',
        'narration' => 'Before reporting period',
        'total_debit' => 100,
        'total_credit' => 100,
        'status' => 'posted',
    ]);

    JournalItem::create([
        'journal_entry_id' => $entry->id,
        'account_id' => $cash->id,
        'debit' => 100,
        'credit' => 0,
    ]);

    $page = new TrialBalanceReport;
    $page->startDate = '2026-01-01';
    $page->endDate = '2026-12-31';

    $rows = financialReportQuery($page, 'accountQuery')->get();

    expect($rows)->toHaveCount(1);

    $row = $rows->first();

    expect((float) $row->debit_total)->toBe(0.0)
        ->and((float) $row->credit_total)->toBe(0.0)
        ->and((float) $row->balance)->toBe(0.0);
});

test('profit and loss report only includes revenue and expense accounts', function (): void {
    Account::create([
        'code' => '1010',
        'name' => 'Cash',
        'type' => 'Asset',
    ]);

    Account::create([
        'code' => '4000',
        'name' => 'Sales Revenue',
        'type' => 'Revenue',
    ]);

    Account::create([
        'code' => '5000',
        'name' => 'Office Expense',
        'type' => 'Expense',
    ]);

    $page = new ProfitLossStatementReport;
    $page->startDate = '2026-01-01';
    $page->endDate = '2026-12-31';

    $rows = financialReportQuery($page, 'accountQuery')->get();

    expect($rows->pluck('type')->all())->toBe(['Revenue', 'Expense']);
});

test('payables aging uses tax inclusive purchase order totals', function (): void {
    $supplier = Partner::factory()->create(['is_supplier' => true]);

    $purchaseOrder = PurchaseOrder::factory()->create([
        'partner_id' => $supplier->id,
        'order_date' => '2026-01-10',
        'subtotal' => 1000,
        'tax_amount' => 150,
        'total' => 1150,
    ]);

    $payment = Payment::factory()->create([
        'partner_id' => $supplier->id,
        'payment_date' => '2026-01-20',
        'amount' => 200,
        'direction' => 'outbound',
    ]);

    PaymentAllocation::create([
        'payment_id' => $payment->id,
        'allocatable_type' => PurchaseOrder::class,
        'allocatable_id' => $purchaseOrder->id,
        'allocated_amount' => 200,
    ]);

    $page = new PayablesAgingReport;
    $page->asOfDate = '2026-01-31';

    $row = financialReportQuery($page, 'agingQuery')->first();

    expect((float) $row->balance)->toBe(950.0);
});

test('account statement running balance includes opening balance', function (): void {
    $cash = Account::create([
        'code' => '1010',
        'name' => 'Cash',
        'type' => 'Asset',
    ]);

    $openingEntry = JournalEntry::create([
        'date' => '2025-12-31',
        'reference' => 'OPEN',
        'narration' => 'Opening',
        'total_debit' => 500,
        'total_credit' => 500,
        'status' => 'posted',
    ]);

    JournalItem::create([
        'journal_entry_id' => $openingEntry->id,
        'account_id' => $cash->id,
        'debit' => 500,
        'credit' => 0,
    ]);

    $periodEntry = JournalEntry::create([
        'date' => '2026-01-10',
        'reference' => 'PERIOD',
        'narration' => 'Period movement',
        'total_debit' => 100,
        'total_credit' => 100,
        'status' => 'posted',
    ]);

    JournalItem::create([
        'journal_entry_id' => $periodEntry->id,
        'account_id' => $cash->id,
        'debit' => 100,
        'credit' => 0,
    ]);

    $page = new AccountStatementReport;
    $page->accountId = $cash->id;
    $page->startDate = '2026-01-01';
    $page->endDate = '2026-01-31';

    $row = financialReportQuery($page, 'statementQuery')->first();

    expect((float) $row->running_balance)->toBe(600.0);
});

function financialReportQuery(object $page, string $method): Builder
{
    return Closure::bind(fn (): Builder => $this->{$method}(), $page, $page::class)();
}

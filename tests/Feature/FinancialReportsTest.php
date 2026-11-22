<?php

use App\Filament\Finance\Pages\AccountStatementReport;
use App\Filament\Finance\Pages\BalanceSheetReport;
use App\Filament\Finance\Pages\IncomeStatementReport;
use App\Filament\Finance\Pages\PayablesAgingReport;
use App\Filament\Finance\Pages\ProfitLossStatementReport;
use App\Filament\Finance\Pages\ReceivablesAgingReport;
use App\Filament\Finance\Pages\TrialBalanceReport;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\JobOrder;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use App\Services\Accounting\ReceivablesQuery;
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

    expect($rows)->toHaveCount(Account::count());

    $row = $rows->firstWhere('id', $cash->id);
    expect($row)->not->toBeNull();

    expect((float) $row->debit_total)->toBe(0.0)
        ->and((float) $row->credit_total)->toBe(0.0)
        ->and((float) $row->balance)->toBe(0.0);
});

test('earnings reports only include revenue and expense accounts', function (string $reportClass): void {
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

    $page = new $reportClass;
    $page->startDate = '2026-01-01';
    $page->endDate = '2026-12-31';

    $rows = financialReportQuery($page, 'accountQuery')->get();

    expect($rows->pluck('type')->unique()->values()->all())->toBe(['Revenue', 'Expense'])
        ->and($rows->pluck('code')->all())->toContain('4000', '5000');
})->with([
    'profit and loss' => [ProfitLossStatementReport::class],
    'income statement' => [IncomeStatementReport::class],
]);

test('balance sheet keeps zero-balance assets and excludes revenue accounts', function (): void {
    $cash = Account::create(['code' => '1010', 'name' => 'Cash', 'type' => 'Asset']);
    $revenue = Account::create(['code' => '4000', 'name' => 'Sales Revenue', 'type' => 'Revenue']);
    $page = new BalanceSheetReport;
    $page->asOfDate = '2026-12-31';

    $rows = financialReportQuery($page, 'accountQuery')->get();

    expect($rows->modelKeys())->toContain($cash->id)->not->toContain($revenue->id)
        ->and((float) $rows->firstWhere('id', $cash->id)->display_amount)->toBe(0.0);
});

test('payables aging uses tax inclusive purchase order totals', function (): void {
    $supplier = Partner::factory()->create(['is_supplier' => true]);

    $purchaseOrder = PurchaseOrder::factory()->create([
        'partner_id' => $supplier->id,
        'order_date' => '2026-01-10',
        'status' => 'approved',
        'subtotal' => 1000,
        'tax_amount' => 150,
        'total' => 1150,
    ]);
    $this->travelTo(now()->setDate(2026, 1, 15));
    $purchaseOrder->update(['status' => 'received']);
    $this->travelBack();

    Payment::factory()->create([
        'partner_id' => $supplier->id,
        'payment_date' => '2026-01-20',
        'amount' => 200,
        'direction' => 'outbound',
        'method' => 'cash',
        'payable_type' => PurchaseOrder::class,
        'payable_id' => $purchaseOrder->id,
    ]);

    $page = new PayablesAgingReport;
    $page->asOfDate = '2026-01-31';

    $row = financialReportQuery($page, 'agingQuery')->first();

    expect((float) $row->balance)->toBe(950.0);
});

test('payables aging distinguishes unposted commitments and restores later voids in historical balances', function (): void {
    $data = ['order_date' => '2026-01-10', 'subtotal' => 1000, 'total' => 1000];
    PurchaseOrder::factory()->create([...$data, 'status' => 'draft']);
    PurchaseOrder::factory()->create([...$data, 'status' => 'approved']);
    PurchaseOrder::factory()->create([...$data, 'status' => 'cancelled']);
    $received = PurchaseOrder::factory()->create([...$data, 'status' => 'approved']);
    $this->travelTo(now()->setDate(2026, 1, 20));
    $received->update(['status' => 'received']);
    $this->travelBack();

    $paymentData = [
        'partner_id' => $received->partner_id,
        'payable_type' => PurchaseOrder::class,
        'payable_id' => $received->id,
        'payment_date' => '2026-01-25',
        'method' => 'cash',
    ];
    Payment::factory()->create([...$paymentData, 'amount' => 300, 'direction' => 'outbound', 'voided_at' => '2026-02-06']);
    Payment::factory()->create([...$paymentData, 'amount' => 400, 'direction' => 'inbound']);
    Payment::factory()->create([...$paymentData, 'amount' => 100, 'direction' => 'outbound'])->delete();

    $page = new PayablesAgingReport;
    $page->asOfDate = '2026-01-19';
    expect(financialReportQuery($page, 'agingQuery')->count())->toBe(0);

    $page->asOfDate = '2026-01-31';
    expect(financialReportQuery($page, 'agingQuery')->pluck('po_number')->all())->toBe([$received->po_number])
        ->and($page->report()['total'])->toBe(700.0);

    $page->asOfDate = '2026-02-06';
    expect($page->report()['total'])->toBe(1000.0);
});

test('payables aging includes historical standalone purchase invoices only when posted to payables', function (): void {
    $supplier = Partner::factory()->create(['is_supplier' => true]);

    $invoice = Invoice::create([
        'invoice_number' => 'PINV-001',
        'invoice_type' => 'purchase',
        'order_type' => 'payment',
        'order_id' => 1,
        'partner_id' => $supplier->id,
        'invoice_date' => '2026-01-10',
        'due_date' => '2026-01-31',
        'subtotal' => 1000,
        'tax_amount' => 150,
        'total_amount' => 1150,
        'balance_due' => 1150,
        'status' => 'unpaid',
        'filename' => 'pinv-001.pdf',
        'file_path' => 'invoices/pinv-001.pdf',
    ]);

    $page = new PayablesAgingReport;
    $page->asOfDate = '2026-01-31';

    expect(financialReportQuery($page, 'agingQuery')->count())->toBe(0);
    $journal = JournalEntry::factory()->create([
        'source_type' => Invoice::class,
        'source_id' => $invoice->id,
        'date' => '2026-01-10',
        'posted_at' => '2026-01-10 08:00:00',
        'status' => 'posted',
        'voided_at' => null,
    ]);
    JournalItem::factory()->create([
        'journal_entry_id' => $journal->id,
        'account_id' => Account::getSystemAccount(Account::CODE_AP, 'Accounts Payable', 'Liability')->id,
        'debit' => 0,
        'credit' => 1150,
    ]);

    $row = financialReportQuery($page, 'agingQuery')->first();

    expect($row->po_number)->toBe('PINV-001')
        ->and((float) $row->balance)->toBe(1150.0);
});

test('receivables aging reconstructs payments and posted sales voided after its cutoff', function (): void {
    $sale = SalesOrder::create([
        'partner_id' => Partner::factory()->create()->id,
        'warehouse_id' => Warehouse::factory()->create()->id,
        'order_date' => '2026-01-10',
        'subtotal' => 1000,
        'total' => 1000,
        'status' => SalesOrder::STATUS_VOID,
    ]);
    JournalEntry::factory()->create([
        'source_type' => SalesOrder::class,
        'source_id' => $sale->id,
        'date' => '2026-01-10',
        'posted_at' => '2026-01-10 08:00:00',
        'voided_at' => '2026-02-06 10:00:00',
        'status' => 'posted',
    ]);
    Payment::withoutEvents(fn () => Payment::factory()->create([
        'partner_id' => $sale->partner_id,
        'payable_type' => SalesOrder::class,
        'payable_id' => $sale->id,
        'payment_date' => '2026-01-20',
        'amount' => 300,
        'direction' => 'inbound',
        'voided_at' => '2026-02-01 10:00:00',
    ]));

    $query = app(ReceivablesQuery::class);
    expect($query->total('2026-01-31'))->toBe(700.0)
        ->and($query->total('2026-02-01'))->toBe(1000.0)
        ->and($query->total('2026-02-06'))->toBe(0.0);
});

test('receivables aging includes job orders with open balances', function (): void {
    $customer = Partner::factory()->create(['is_customer' => true]);

    JobOrder::create([
        'name' => 'Printing', 'quantity' => 100,
        'partner_id' => $customer->id,
        'job_type' => 'books',
        'cost_calc_file' => 'aging.pdf',
        'services' => ['printing'],
        'submission_date' => '2026-01-10',
        'advance_amount' => 0,
        'subtotal' => 700,
        'tax_amount' => 0,
        'total' => 700,
        'status' => 'design',
    ]);

    $page = new ReceivablesAgingReport;
    $page->asOfDate = '2026-01-31';

    $row = financialReportQuery($page, 'agingQuery')->first();

    expect($row->order_number)->toStartWith('JO-')
        ->and((float) $row->balance)->toBe(700.0);
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

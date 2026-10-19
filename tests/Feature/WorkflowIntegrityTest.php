<?php

use App\Filament\Finance\Pages\GeneralLedgerReport;
use App\Filament\Resources\BankTransfers\Pages\EditBankTransfer;
use App\Filament\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\Resources\PurchaseOrderItems\Pages\EditPurchaseOrderItem;
use App\Filament\Resources\PurchaseOrderItems\PurchaseOrderItemResource;
use App\Filament\Resources\PurchaseOrders\Pages\ListPurchaseOrders;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Resources\SalesOrders\Pages\ListSalesOrders;
use App\Filament\Resources\StockTransfers\Pages\EditStockTransfer;
use App\Models\Account;
use App\Models\AccountingIntegration;
use App\Models\Bank;
use App\Models\BankTransfer;
use App\Models\Bond;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\GoodsReceipt;
use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\PayrollRun;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\SalesOrder;
use App\Models\Setting;
use App\Models\StockAdjustment;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Accounting\FinancialReportService;
use App\Services\Accounting\GenerateAccountingExport;
use App\Services\Accounting\VoidPaymentJournalEntry;
use App\Services\Hr\GeneratePayrollPayments;
use App\Services\Hr\PostPayrollRun;
use App\Services\Hr\RepayEmployeeLoan;
use App\Services\VoidSalesOrder;
use App\UserRole;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);
beforeEach(function (): void {
    Notification::fake();
    $this->restoreRepeaterKeys = Repeater::fake();
    Setting::getSettings()->update(['vat_enabled' => false]);
});

afterEach(function (): void {
    ($this->restoreRepeaterKeys)();
});

it('duplicate sale lines deduct their combined quantity and void restores stock', function (): void {
    [$sale, $item, $warehouse] = workflowSale([3, 4]);
    $sale->update(['status' => 'completed']);
    expect(workflowStock($item, $warehouse))->toBe(13.0);
    workflowFinance();
    Livewire::test(ListSalesOrders::class)
        ->callAction(TestAction::make('void')->table($sale));
    expect(workflowStock($item, $warehouse))->toBe(20.0);
});

it('voiding a sale reverses its revenue journal', function (): void {
    [$sale] = workflowSale([5]);
    $sale->update(['status' => 'completed']);
    workflowFinance();
    Livewire::test(ListSalesOrders::class)
        ->callAction(TestAction::make('void')->table($sale));
    expect($sale->fresh()->status)->toBe('void');
    $revenue = Account::where('code', '4000')->firstOrFail();
    expect((float) JournalItem::where('account_id', $revenue->id)->sum('credit') - (float) JournalItem::where('account_id', $revenue->id)->sum('debit'))->toBe(0.0)
        ->and(JournalEntry::where('source_type', SalesOrder::class)->where('source_id', $sale->id)->count())->toBe(2);
});

it('failed cash sale completion rolls back status stock and journals', function (): void {
    [$sale, $item, $warehouse] = workflowSale([5]);
    $sale->update(['payment_method' => 'bank', 'bank_id' => null]);
    expect(fn () => $sale->update(['status' => 'completed']))->toThrow(RuntimeException::class);
    expect($sale->fresh()->status)->toBe('draft')->and(workflowStock($item, $warehouse))->toBe(20.0)
        ->and($sale->payments()->count())->toBe(0);
});

it('receive action rejects quantities above the remaining order amount', function (): void {
    [$po, $line, $warehouse] = workflowPurchase();
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    Livewire::test(ListPurchaseOrders::class)
        ->callAction(TestAction::make('receive')->table($po), ['warehouse_id' => $warehouse->id, 'receipt_date' => today()->toDateString(), 'items' => [['purchase_order_item_id' => $line->id, 'quantity_received' => 20]]])
        ->assertHasActionErrors(['items.0.quantity_received' => 'max']);
    expect((float) $line->fresh()->received_quantity)->toBe(0.0);
    expect(GoodsReceipt::count())->toBe(0);
});

it('receipt rejects lines belonging to a different purchase order', function (): void {
    [$po, , $warehouse] = workflowPurchase();
    [$other, $line] = workflowPurchase();
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    Livewire::test(ListPurchaseOrders::class)
        ->callAction(TestAction::make('receive')->table($po), ['warehouse_id' => $warehouse->id, 'receipt_date' => today()->toDateString(), 'items' => [['purchase_order_item_id' => $line->id, 'quantity_received' => 10]]])
        ->assertHasNoFormErrors();
    expect((float) $line->fresh()->received_quantity)->toBe(0.0)
        ->and($other->fresh()->status)->toBe('approved')
        ->and(GoodsReceipt::where('purchase_order_id', $po->id)->exists())->toBeFalse();
});

it('received purchase lines cannot be repriced', function (): void {
    [$po, $line, $warehouse] = workflowPurchase();
    $receipt = GoodsReceipt::create(['purchase_order_id' => $po->id, 'warehouse_id' => $warehouse->id, 'receipt_date' => today(), 'status' => 'draft']);
    $receipt->items()->create(['purchase_order_item_id' => $line->id, 'quantity_received' => 10]);
    $receipt->update(['status' => 'posted']);
    Filament::setCurrentPanel(Filament::getPanel('operations'));
    $this->actingAs(User::factory()->create(['role' => UserRole::Operations]));
    Livewire::test(EditPurchaseOrderItem::class, ['record' => $line->id])
        ->assertRedirect();
    expect(fn () => $line->update(['unit_price' => 20, 'total' => 200]))->toThrow(RuntimeException::class);
    expect(PurchaseOrderItemResource::canEdit($line))->toBeFalse();
    expect((float) $po->fresh()->total)->toBe(100.0)
        ->and((float) JournalEntry::where('source_type', PurchaseOrder::class)->where('source_id', $po->id)->value('total_credit'))->toBe(100.0);
});

it('a stale bank transfer cannot be completed twice', function (): void {
    $from = workflowBank(1000);
    $to = workflowBank(0);
    $transfer = BankTransfer::create(['from_bank_id' => $from->id, 'to_bank_id' => $to->id, 'amount' => 100, 'transfer_date' => today(), 'status' => 'pending']);
    $stale = $transfer->fresh();
    $transfer->complete();
    expect(fn () => $stale->complete())->toThrow(Exception::class);
    expect((float) $from->fresh()->current_balance)->toBe(900.0)->and((float) $to->fresh()->current_balance)->toBe(100.0);
});

it('completed bank transfers cannot be edited', function (): void {
    $from = workflowBank(1000);
    $to = workflowBank(0);
    $transfer = BankTransfer::create(['from_bank_id' => $from->id, 'to_bank_id' => $to->id, 'amount' => 100, 'transfer_date' => today(), 'status' => 'pending']);
    $transfer->complete();
    workflowFinance();
    Livewire::test(EditBankTransfer::class, ['record' => $transfer->id])
        ->assertRedirect();
    expect(fn () => $transfer->update(['amount' => 200]))->toThrow(RuntimeException::class);
    expect((float) $from->fresh()->current_balance)->toBe(900.0)->and($from->fresh()->expected_balance)->toBe(900.0);
});

it('stale stock adjustments post only once', function (): void {
    $warehouse = Warehouse::factory()->create();
    $item = InventoryItem::factory()->create();
    $adjustment = StockAdjustment::create(['warehouse_id' => $warehouse->id, 'adjustment_date' => today(), 'status' => 'draft', 'reason' => 'Count']);
    $adjustment->items()->create(['inventory_item_id' => $item->id, 'system_quantity' => 0, 'actual_quantity' => 5, 'adjustment_quantity' => 5]);
    $stale = $adjustment->fresh();
    $adjustment->post();
    $stale->post();
    expect(workflowStock($item, $warehouse))->toBe(5.0);
});

it('voiding a loan repayment restores the outstanding balance', function (): void {
    $loan = workflowLoan();
    $payment = app(RepayEmployeeLoan::class)->handle($loan, 'cash');
    app(VoidPaymentJournalEntry::class)->handle($payment, 'Wrong repayment');
    expect($loan->fresh()->remainingBalance())->toBe(100.0)->and($loan->fresh()->status)->toBe('active');
});

it('voiding payroll payment reopens payroll for replacement payment', function (): void {
    $run = workflowPayroll();
    app(GeneratePayrollPayments::class)->handle($run, 'cash');
    $payment = Payment::findOrFail($run->employees()->value('payment_id'));
    app(VoidPaymentJournalEntry::class)->handle($payment, 'Wrong payroll');
    expect($run->fresh()->status)->toBe('approved')->and($run->employees()->value('payment_id'))->toBeNull();
    app(GeneratePayrollPayments::class)->handle($run->fresh(), 'cash');
    expect($run->fresh()->status)->toBe('paid')->and($run->employees()->value('payment_id'))->not->toEqual($payment->id);
});

it('a stale payroll snapshot cannot generate a second payment', function (): void {
    $run = workflowPayroll();
    $stale = $run->fresh()->load('employees.employee');
    app(GeneratePayrollPayments::class)->handle($run, 'cash');
    expect(fn () => app(GeneratePayrollPayments::class)->handle($stale, 'cash'))->toThrow(RuntimeException::class);
    expect(Payment::where('transaction_type', 'payroll_payment')->count())->toBe(1);
});

it('a zero payroll loan deduction leaves the installment unpaid', function (): void {
    $loan = workflowLoan();
    $run = PayrollRun::factory()->create();
    $run->employees()->create(['employee_id' => $loan->employee_id, 'basic_salary' => 1000, 'loan' => 0, 'calculation_snapshot' => ['pension_enabled' => false, 'tax' => ['rate' => 0, 'deduction' => 0], 'loan_installment_ids' => $loan->installments()->pluck('id')->all()]]);
    app(PostPayrollRun::class)->handle($run);
    expect($loan->fresh()->remainingBalance())->toBe(100.0)
        ->and((float) $run->employees()->sum('loan'))->toBe(0.0);
});

it('bonds cannot be recovered again from a stale snapshot', function (): void {
    $bond = Bond::factory()->create(['amount' => 100, 'status' => 'pending']);
    $bond->issue(today()->toDateString(), 'cash');
    $stale = $bond->fresh();
    $bond->recover(today()->toDateString(), 'cash');
    expect(fn () => $stale->recover(today()->toDateString(), 'cash'))->toThrow(RuntimeException::class);
    expect($bond->payments()->where('direction', 'inbound')->count())->toBe(1);
});

it('invoices do not charge VAT when disabled', function (): void {
    workflowFinance();
    Setting::getSettings()->update(['vat_enabled' => false, 'vat_rate' => 15]);
    Livewire::test(CreateInvoice::class)
        ->set('data.subtotal', 100)->assertFormSet(['tax_amount' => 0.0, 'total_amount' => 100.0]);
});

it('manual payroll deductions post a balanced journal', function (): void {
    $run = PayrollRun::factory()->create();
    $run->employees()->create(['employee_id' => Employee::factory()->create()->id, 'basic_salary' => 1000, 'calculation_snapshot' => ['pension_enabled' => false, 'union_enabled' => false, 'tax' => ['rate' => 0, 'deduction' => 0], 'manual_deductions' => [['amount' => 100]]]]);
    $journal = app(PostPayrollRun::class)->handle($run);
    expect((float) $journal->total_debit)->toBe(1000.0)->and((float) $journal->total_credit)->toBe(1000.0);
});

it('voiding full payment reopens a credit sale', function (): void {
    [$sale] = workflowSale([5]);
    $sale->update(['payment_mode' => 'credit']);
    $sale->update(['status' => 'submitted']);
    $payment = Payment::create(['partner_id' => $sale->partner_id, 'amount' => 50, 'direction' => 'inbound', 'transaction_type' => 'customer_receipt', 'method' => 'cash', 'payment_date' => today(), 'payable_type' => SalesOrder::class, 'payable_id' => $sale->id]);
    expect($sale->fresh()->status)->toBe('completed');
    app(VoidPaymentJournalEntry::class)->handle($payment, 'Wrong receipt');
    expect($sale->fresh()->status)->toBe('submitted')->and($sale->fresh()->balance)->toEqual(50);
});

it('completed stock transfer cannot be reassigned', function (): void {
    [$sale, $item, $from] = workflowSale([5]);
    $to = Warehouse::factory()->create();
    $other = Warehouse::factory()->create();
    $transfer = StockTransfer::create(['from_warehouse_id' => $from->id, 'to_warehouse_id' => $to->id, 'transfer_date' => today(), 'status' => 'draft']);
    $transfer->items()->create(['inventory_item_id' => $item->id, 'quantity' => 5]);
    $transfer->post();
    Filament::setCurrentPanel(Filament::getPanel('warehouse'));
    $this->actingAs(User::factory()->create(['role' => UserRole::Warehouse]));
    Livewire::test(EditStockTransfer::class, ['record' => $transfer->id])
        ->assertRedirect();
    expect(fn () => $transfer->update(['to_warehouse_id' => $other->id]))->toThrow(RuntimeException::class);
    expect($transfer->fresh()->to_warehouse_id)->toBe($to->id)->and(workflowStock($item, $to))->toBe(5.0)->and(workflowStock($item, $other))->toBe(0.0);
});

it('filtered general ledger preserves opening balances', function (): void {
    $account = Account::getSystemAccount('1000', 'Cash', 'Asset');
    foreach (['2026-09-01' => 100, '2026-10-01' => 20] as $date => $amount) {
        $journal = JournalEntry::create(['date' => $date, 'reference' => $date, 'status' => 'posted', 'posted_at' => now(), 'total_debit' => $amount, 'total_credit' => $amount]);
        JournalItem::create(['journal_entry_id' => $journal->id, 'account_id' => $account->id, 'debit' => $amount, 'credit' => 0]);
    }
    $report = app(FinancialReportService::class)->generalLedger('2026-10-01', '2026-10-31', $account->id);
    expect((float) $report['rows']->first()->running_balance)->toBe(120.0);
});

it('accounting exports with the same cutoff preserve earlier workbooks', function (): void {
    config()->set('filesystems.private_disk', 'local');
    Storage::fake('local');
    $integration = AccountingIntegration::factory()->create(['provider' => AccountingIntegration::PROVIDER_PEACHTREE_DESKTOP]);
    $debit = Account::getSystemAccount('1000', 'Cash', 'Asset');
    $credit = Account::getSystemAccount('4000', 'Sales', 'Revenue');
    $cutoff = now()->addDay();
    $exports = [];
    foreach ([100, 200] as $amount) {
        $journal = JournalEntry::create(['date' => today(), 'reference' => 'Audit '.$amount, 'status' => 'posted', 'posted_at' => now(), 'total_debit' => $amount, 'total_credit' => $amount]);
        JournalItem::create(['journal_entry_id' => $journal->id, 'account_id' => $debit->id, 'debit' => $amount, 'credit' => 0]);
        JournalItem::create(['journal_entry_id' => $journal->id, 'account_id' => $credit->id, 'debit' => 0, 'credit' => $amount]);
        $exports[] = app(GenerateAccountingExport::class)->handle($integration, $cutoff);
    }
    expect($exports[0]->status)->toBe('completed')->and($exports[1]->status)->toBe('completed')
        ->and($exports[1]->file_path)->not->toBe($exports[0]->file_path)
        ->and($exports[1]->checksum)->not->toBe($exports[0]->checksum)
        ->and(hash('sha256', Storage::disk('local')->get($exports[0]->file_path)))->toBe($exports[0]->checksum);
});

it('stale payment cannot be reversed or refunded twice', function (): void {
    $bank = workflowBank(1000);
    $payment = Payment::create(['partner_id' => Partner::factory()->create(['is_supplier' => true])->id, 'amount' => 100, 'direction' => 'outbound', 'transaction_type' => 'supplier_payment', 'method' => 'bank', 'bank_id' => $bank->id, 'payment_date' => today()]);
    $stale = $payment->fresh();
    app(VoidPaymentJournalEntry::class)->handle($payment, 'First reversal');
    expect(fn () => app(VoidPaymentJournalEntry::class)->handle($stale, 'Duplicate reversal'))->toThrow(RuntimeException::class);
    expect((float) $bank->fresh()->current_balance)->toBe(1000.0)
        ->and($payment->journalEntries()->whereNotNull('reversal_of_journal_entry_id')->count())->toBe(1);
});

it('warehouse users can receive purchases through their own panel', function (): void {
    [$po, $line, $warehouse] = workflowPurchase();
    Filament::setCurrentPanel(Filament::getPanel('warehouse'));
    $this->actingAs(User::factory()->create(['role' => UserRole::Warehouse]));
    expect(Route::has('filament.warehouse.resources.purchase-orders.view'))->toBeTrue();
    Livewire::test(ListPurchaseOrders::class)
        ->assertActionVisible(TestAction::make('receive')->table($po))
        ->assertActionHidden(TestAction::make('approve')->table($po))
        ->callAction(TestAction::make('receive')->table($po), ['warehouse_id' => $warehouse->id, 'receipt_date' => today()->toDateString(), 'items' => [['purchase_order_item_id' => $line->id, 'quantity_received' => 10]]])
        ->assertHasNoActionErrors();
    expect($po->fresh()->status)->toBe('received');
    expect(PurchaseOrderResource::canEdit($po))->toBeFalse();
    expect(PurchaseOrderResource::canDelete($po))->toBeFalse();
});

it('voiding one of several partial loan repayments preserves the other allocations', function (): void {
    $loan = workflowLoan();
    $first = app(RepayEmployeeLoan::class)->handle($loan, 'cash', amount: 40);
    $second = app(RepayEmployeeLoan::class)->handle($loan, 'cash', amount: 60);
    app(VoidPaymentJournalEntry::class)->handle($first, 'Wrong first receipt');
    expect($loan->fresh()->remainingBalance())->toBe(40.0);
    app(VoidPaymentJournalEntry::class)->handle($second, 'Wrong second receipt');
    expect($loan->fresh()->remainingBalance())->toBe(100.0);
});

it('a reduced payroll deduction only pays that amount of the installment', function (): void {
    $loan = workflowLoan();
    $run = PayrollRun::factory()->create();
    $run->employees()->create(['employee_id' => $loan->employee_id, 'basic_salary' => 1000, 'loan' => 40, 'calculation_snapshot' => ['pension_enabled' => false, 'union_enabled' => false, 'tax' => ['rate' => 0, 'deduction' => 0], 'loan_installment_ids' => $loan->installments()->pluck('id')->all()]]);
    app(PostPayrollRun::class)->handle($run);
    expect($loan->fresh()->remainingBalance())->toBe(60.0)->and($loan->installments()->first()->status)->toBe('pending');
});

it('sale stock validation combines duplicate lines and rolls back on insufficient combined stock', function (): void {
    [$sale, $item, $warehouse] = workflowSale([12, 12]);
    expect(fn () => $sale->update(['status' => 'completed']))->toThrow(RuntimeException::class);
    expect($sale->fresh()->status)->toBe('draft')->and(workflowStock($item, $warehouse))->toBe(20.0);
    expect(JournalEntry::count())->toBe(0);
});

it('voiding a bank cash sale restores its bank balance and is idempotent', function (): void {
    [$sale, $item, $warehouse] = workflowSale([5]);
    $bank = workflowBank(1000);
    $sale->update(['payment_method' => 'bank', 'bank_id' => $bank->id]);
    $sale->update(['status' => 'completed']);
    expect((float) $bank->fresh()->current_balance)->toBe(1050.0);
    app(VoidSalesOrder::class)->handle($sale);
    app(VoidSalesOrder::class)->handle($sale);
    expect((float) $bank->fresh()->current_balance)->toBe(1000.0)->and(workflowStock($item, $warehouse))->toBe(20.0);
});

it('cancelled purchase orders and duplicate receipt rows cannot post stock', function (): void {
    [$po, $line, $warehouse] = workflowPurchase();
    $receipt = GoodsReceipt::create(['purchase_order_id' => $po->id, 'warehouse_id' => $warehouse->id, 'receipt_date' => today(), 'status' => 'draft']);
    foreach ([6, 6] as $quantity) {
        $receipt->items()->create(['purchase_order_item_id' => $line->id, 'quantity_received' => $quantity]);
    }
    expect(fn () => $receipt->update(['status' => 'posted']))->toThrow(RuntimeException::class);
    expect($receipt->fresh()->status)->toBe('draft')->and((float) $line->fresh()->received_quantity)->toBe(0.0);
    $po->update(['status' => 'cancelled']);
    expect(fn () => $receipt->fresh()->update(['status' => 'posted']))->toThrow(RuntimeException::class);
});

it('completed transfers and their lines cannot be deleted or changed', function (): void {
    [$sale, $item, $from] = workflowSale([5]);
    $to = Warehouse::factory()->create();
    $transfer = StockTransfer::create(['from_warehouse_id' => $from->id, 'to_warehouse_id' => $to->id, 'transfer_date' => today(), 'status' => 'draft']);
    $line = $transfer->items()->create(['inventory_item_id' => $item->id, 'quantity' => 5]);
    $transfer->post();
    expect(fn () => $line->update(['quantity' => 1]))->toThrow(RuntimeException::class);
    expect(fn () => $line->fresh()->delete())->toThrow(RuntimeException::class);
    expect(fn () => $transfer->delete())->toThrow(RuntimeException::class);
    $bankTransfer = BankTransfer::create(['from_bank_id' => workflowBank(1000)->id, 'to_bank_id' => workflowBank(0)->id, 'amount' => 100, 'transfer_date' => today(), 'status' => 'pending']);
    $bankTransfer->complete();
    expect(fn () => $bankTransfer->delete())->toThrow(RuntimeException::class);
});

it('the ledger screen preserves opening balances with search and account filters', function (): void {
    workflowFinance();
    $account = Account::getSystemAccount('1000', 'Cash', 'Asset');
    $items = [];
    foreach (['2026-09-01' => 100, '2026-10-01' => 20] as $date => $amount) {
        $journal = JournalEntry::create(['date' => $date, 'reference' => $date, 'status' => 'posted', 'posted_at' => now(), 'total_debit' => $amount, 'total_credit' => $amount]);
        $items[] = JournalItem::create(['journal_entry_id' => $journal->id, 'account_id' => $account->id, 'debit' => $amount, 'credit' => 0]);
    }
    Livewire::test(GeneralLedgerReport::class)
        ->set('startDate', '2026-10-01')->set('endDate', '2026-10-31')->set('accountId', $account->id)
        ->filterTable('account_type', 'Asset')->searchTable('Cash')
        ->assertCanSeeTableRecords([$items[1]])->assertCanNotSeeTableRecords([$items[0]])
        ->assertTableColumnStateSet('running_balance', 120, $items[1]);
});

it('stale bonds cannot be issued twice', function (): void {
    $bond = Bond::factory()->create(['amount' => 100, 'status' => 'pending']);
    $stale = $bond->fresh();
    $bond->issue(today()->toDateString(), 'cash');
    expect(fn () => $stale->issue(today()->toDateString(), 'cash'))->toThrow(RuntimeException::class);
    expect($bond->payments()->where('direction', 'outbound')->count())->toBe(1);
});

it('invalid adjustment posting rolls back its status and inventory', function (): void {
    $warehouse = Warehouse::factory()->create();
    $item = InventoryItem::factory()->create();
    $adjustment = StockAdjustment::create(['warehouse_id' => $warehouse->id, 'adjustment_date' => today(), 'status' => 'draft', 'reason' => 'Count']);
    $adjustment->items()->create(['inventory_item_id' => $item->id, 'system_quantity' => 0, 'adjustment_quantity' => -5]);
    expect(fn () => $adjustment->update(['status' => 'posted']))->toThrow(Exception::class);
    expect($adjustment->fresh()->status)->toBe('draft')->and(workflowStock($item, $warehouse))->toBe(0.0);
});

it('enabled invoice VAT is rounded to currency precision', function (): void {
    workflowFinance();
    Setting::getSettings()->update(['vat_enabled' => true, 'vat_rate' => 15]);
    Livewire::test(CreateInvoice::class)
        ->set('data.subtotal', 0.11)->assertFormSet(['tax_amount' => 0.02, 'total_amount' => 0.13]);
});

function workflowFinance(): void
{
    Filament::setCurrentPanel(Filament::getPanel('finance'));
    test()->actingAs(User::factory()->create(['role' => UserRole::Finance]));
}

function workflowStock(InventoryItem $item, Warehouse $warehouse): float
{
    return (float) InventoryBalance::where('inventory_item_id', $item->id)->where('warehouse_id', $warehouse->id)->value('quantity_on_hand');
}

function workflowBank(float $balance): Bank
{
    return Bank::create(['name' => fake()->unique()->company(), 'code' => fake()->unique()->bothify('B-###'), 'account_number' => fake()->numerify('########'), 'account_holder_name' => 'Demo', 'bank_name' => 'Demo', 'current_balance' => $balance, 'status' => 'active']);
}

function workflowSale(array $quantities): array
{
    $warehouse = Warehouse::factory()->create();
    $item = InventoryItem::factory()->create(['unit' => 'pcs', 'purchase_unit' => null, 'is_sellable' => true, 'type' => 'finished_good']);
    InventoryBalance::create(['warehouse_id' => $warehouse->id, 'inventory_item_id' => $item->id, 'quantity_on_hand' => 20]);
    $sale = SalesOrder::create(['warehouse_id' => $warehouse->id, 'partner_id' => Partner::factory()->create()->id, 'order_date' => today(), 'payment_mode' => 'cash', 'payment_method' => 'cash', 'status' => 'draft']);
    foreach ($quantities as $quantity) {
        $sale->salesOrderItems()->create(['inventory_item_id' => $item->id, 'quantity' => $quantity, 'unit_label' => 'pcs', 'unit_price' => 10, 'total' => $quantity * 10]);
    }

    return [$sale->refresh(), $item, $warehouse];
}

function workflowPurchase(): array
{
    $po = PurchaseOrder::factory()->create(['status' => 'approved']);
    $item = InventoryItem::factory()->create(['purchase_unit' => null, 'unit' => 'pcs', 'conversion_factor' => 1]);
    $line = PurchaseOrderItem::factory()->create(['purchase_order_id' => $po->id, 'inventory_item_id' => $item->id, 'quantity' => 10, 'unit_price' => 10, 'total' => 100]);

    return [$po->refresh(), $line, Warehouse::factory()->create()];
}

function workflowLoan(): EmployeeLoan
{
    return EmployeeLoan::create(['employee_id' => Employee::factory()->create()->id, 'loan_date' => today(), 'return_date' => today(), 'amount' => 100, 'installment_count' => 1, 'status' => 'active']);
}

function workflowPayroll(): PayrollRun
{
    $run = PayrollRun::factory()->create();
    $run->employees()->create(['employee_id' => Employee::factory()->create()->id, 'basic_salary' => 100, 'calculation_snapshot' => ['pension_enabled' => false, 'tax' => ['rate' => 0, 'deduction' => 0]]]);
    app(PostPayrollRun::class)->handle($run);

    return $run;
}

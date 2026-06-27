<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Bank;
use App\Models\BankTransaction;
use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\JobOrder;
use App\Models\JournalEntry;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Warehouse;
use App\Services\Accounting\VoidPaymentJournalEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_order_receipt_posts_purchase_journal_entry()
    {
        $supplier = Partner::create([
            'name' => 'Supplier Accounting',
            'is_supplier' => true,
        ]);

        $purchaseOrder = PurchaseOrder::create([
            'po_number' => 'PO-ACC-001',
            'partner_id' => $supplier->id,
            'order_date' => now(),
            'status' => 'draft',
            'subtotal' => 10000.00,
        ]);

        $inventoryItem = InventoryItem::create([
            'name' => 'Office Paper',
            'sku' => 'PAPER-01',
            'unit' => 'Ream',
            'purchase_unit' => 'Ream',
            'conversion_factor' => 1,
            'type' => 'raw_material',
            'is_sellable' => false,
            'price' => 5.00,
            'average_cost' => 5.00,
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $purchaseOrder->id,
            'inventory_item_id' => $inventoryItem->id,
            'quantity' => 1,
            'unit_price' => 10000.00,
            'total' => 10000.00,
        ]);

        $purchaseOrder->update(['status' => 'received']);

        $this->assertDatabaseHas('journal_entries', [
            'source_type' => PurchaseOrder::class,
            'source_id' => $purchaseOrder->id,
            'status' => 'posted',
            'total_debit' => 10000.00,
            'total_credit' => 10000.00,
        ]);

        $journalEntry = JournalEntry::where('source_type', PurchaseOrder::class)
            ->where('source_id', $purchaseOrder->id)
            ->first();

        $this->assertNotNull($journalEntry);
        $this->assertDatabaseHas('journal_items', [
            'journal_entry_id' => $journalEntry->id,
            'debit' => 10000.00,
        ]);
    }

    public function test_inbound_payment_creates_payment_journal_entry()
    {
        $customer = Partner::create([
            'name' => 'Customer Accounting',
            'is_customer' => true,
        ]);

        $payment = Payment::create([
            'payment_number' => 'PAY-001',
            'partner_id' => $customer->id,
            'payment_date' => now(),
            'amount' => 2500.00,
            'direction' => 'inbound',
            'method' => 'bank_transfer',
            'reference' => 'Customer Payment',
        ]);

        $this->assertDatabaseHas('journal_entries', [
            'source_type' => Payment::class,
            'source_id' => $payment->id,
            'status' => 'posted',
            'total_debit' => 2500.00,
            'total_credit' => 2500.00,
        ]);

        $this->assertDatabaseHas('journal_items', [
            'debit' => 2500.00,
        ]);

        $this->assertDatabaseHas('journal_items', [
            'credit' => 2500.00,
        ]);
    }

    public function test_credit_sale_posts_receivable_before_payment_and_collection_clears_receivable()
    {
        $warehouse = Warehouse::create(['name' => 'Credit WH', 'code' => 'CWH']);
        $customer = Partner::create(['name' => 'Credit Customer', 'is_customer' => true]);
        $item = InventoryItem::create([
            'name' => 'Credit Item',
            'sku' => 'CR-ITEM',
            'unit' => 'pcs',
            'purchase_unit' => 'pcs',
            'conversion_factor' => 1,
            'type' => 'finished_good',
            'is_sellable' => true,
            'price' => 100.00,
            'average_cost' => 80.00,
        ]);
        InventoryBalance::create(['inventory_item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity_on_hand' => 10]);

        $order = SalesOrder::create([
            'warehouse_id' => $warehouse->id,
            'partner_id' => $customer->id,
            'order_date' => now(),
            'payment_mode' => 'credit',
            'status' => 'draft',
            'subtotal' => 1000.00,
            'tax_amount' => 0.00,
            'total' => 1000.00,
        ]);
        SalesOrderItem::create(['sales_order_id' => $order->id, 'inventory_item_id' => $item->id, 'quantity' => 10, 'unit_price' => 100.00, 'total' => 1000.00]);

        $order->update(['status' => 'submitted']);

        $salesEntry = JournalEntry::where('source_type', SalesOrder::class)->where('source_id', $order->id)->firstOrFail();
        $cashAccount = Account::getSystemAccount('1000', 'Cash in Hand', 'Asset');
        $receivableAccount = Account::getSystemAccount(Account::CODE_AR, 'Accounts Receivable', 'Asset');
        $revenueAccount = Account::getSystemAccount('4000', 'Sales Revenue', 'Revenue');

        $this->assertDatabaseHas('journal_items', ['journal_entry_id' => $salesEntry->id, 'account_id' => $receivableAccount->id, 'debit' => $salesEntry->total_debit]);
        $this->assertDatabaseHas('journal_items', ['journal_entry_id' => $salesEntry->id, 'account_id' => $revenueAccount->id, 'credit' => 1000.00]);

        $payment = Payment::create([
            'partner_id' => $customer->id,
            'payment_date' => now(),
            'amount' => $salesEntry->total_debit,
            'method' => 'cash',
            'transaction_type' => 'customer_receipt',
            'payable_type' => SalesOrder::class,
            'payable_id' => $order->id,
        ]);
        $paymentEntry = JournalEntry::where('source_type', Payment::class)->where('source_id', $payment->id)->firstOrFail();

        $this->assertDatabaseHas('journal_items', ['journal_entry_id' => $paymentEntry->id, 'account_id' => $cashAccount->id, 'debit' => $salesEntry->total_debit]);
        $this->assertDatabaseHas('journal_items', ['journal_entry_id' => $paymentEntry->id, 'account_id' => $receivableAccount->id, 'credit' => $salesEntry->total_debit]);
    }

    public function test_cash_sale_posts_cash_and_revenue_without_receipt_journal()
    {
        $warehouse = Warehouse::create(['name' => 'Cash WH', 'code' => 'CSH']);
        $customer = Partner::create(['name' => 'Cash Customer', 'is_customer' => true]);
        $item = InventoryItem::create([
            'name' => 'Cash Item',
            'sku' => 'CA-ITEM',
            'unit' => 'pcs',
            'purchase_unit' => 'pcs',
            'conversion_factor' => 1,
            'type' => 'finished_good',
            'is_sellable' => true,
            'price' => 50.00,
            'average_cost' => 40.00,
        ]);
        InventoryBalance::create(['inventory_item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity_on_hand' => 10]);

        $order = SalesOrder::create([
            'warehouse_id' => $warehouse->id,
            'partner_id' => $customer->id,
            'order_date' => now(),
            'payment_mode' => 'cash',
            'payment_method' => 'cash',
            'status' => 'draft',
            'subtotal' => 500.00,
            'tax_amount' => 0.00,
            'total' => 500.00,
        ]);
        SalesOrderItem::create(['sales_order_id' => $order->id, 'inventory_item_id' => $item->id, 'quantity' => 10, 'unit_price' => 50.00, 'total' => 500.00]);

        $order->update(['status' => 'completed']);

        $salesEntry = JournalEntry::where('source_type', SalesOrder::class)->where('source_id', $order->id)->firstOrFail();
        $cashAccount = Account::getSystemAccount('1000', 'Cash in Hand', 'Asset');
        $revenueAccount = Account::getSystemAccount('4000', 'Sales Revenue', 'Revenue');

        $this->assertDatabaseHas('journal_items', ['journal_entry_id' => $salesEntry->id, 'account_id' => $cashAccount->id, 'debit' => $salesEntry->total_debit]);
        $this->assertDatabaseHas('journal_items', ['journal_entry_id' => $salesEntry->id, 'account_id' => $revenueAccount->id, 'credit' => 500.00]);
        $this->assertDatabaseMissing('journal_entries', ['source_type' => Payment::class]);
    }

    public function test_completed_job_order_posts_cash_and_revenue()
    {
        $customer = Partner::create(['name' => 'Job Customer', 'is_customer' => true]);
        $jobOrder = JobOrder::create([
            'partner_id' => $customer->id,
            'job_type' => 'books',
            'cost_calc_file' => 'job.pdf',
            'services' => ['printing'],
            'submission_date' => now(),
            'advance_amount' => 0,
            'subtotal' => 800.00,
            'tax_amount' => 0.00,
            'total' => 800.00,
            'status' => 'active',
        ]);

        $jobOrder->update(['status' => 'completed']);

        $entry = JournalEntry::where('source_type', JobOrder::class)->where('source_id', $jobOrder->id)->firstOrFail();
        $cashAccount = Account::getSystemAccount('1000', 'Cash in Hand', 'Asset');
        $revenueAccount = Account::getSystemAccount('4000', 'Sales Revenue', 'Revenue');

        $this->assertDatabaseHas('journal_items', ['journal_entry_id' => $entry->id, 'account_id' => $cashAccount->id, 'debit' => 800.00]);
        $this->assertDatabaseHas('journal_items', ['journal_entry_id' => $entry->id, 'account_id' => $revenueAccount->id, 'credit' => 800.00]);
    }

    public function test_job_order_payment_withholding_posts_net_cash_and_withholding_receivable()
    {
        $customer = Partner::create(['name' => 'Withholding Customer', 'is_customer' => true]);
        $bank = Bank::create([
            'name' => 'Withholding Bank',
            'code' => 'WHB',
            'account_number' => '1234567890',
            'account_holder_name' => 'PrintOS',
            'bank_name' => 'Withholding Bank',
            'branch' => 'Main',
            'current_balance' => 0,
            'status' => 'active',
        ]);
        $jobOrder = JobOrder::create([
            'partner_id' => $customer->id,
            'job_type' => 'books',
            'cost_calc_file' => 'withholding.pdf',
            'services' => ['printing'],
            'submission_date' => now(),
            'advance_amount' => 0,
            'subtotal' => 1000.00,
            'tax_amount' => 0.00,
            'total' => 1000.00,
            'status' => 'active',
        ]);

        $payment = Payment::create([
            'partner_id' => $customer->id,
            'payment_date' => now(),
            'amount' => 1000.00,
            'withholding_amount' => 20.00,
            'method' => 'bank',
            'bank_id' => $bank->id,
            'transaction_type' => 'customer_receipt',
            'payable_type' => JobOrder::class,
            'payable_id' => $jobOrder->id,
        ]);

        $entry = JournalEntry::where('source_type', Payment::class)->where('source_id', $payment->id)->firstOrFail();
        $bankAccount = Account::getSystemAccount('1010', 'Bank Current Account', 'Asset');
        $withholdingAccount = Account::getSystemAccount(Account::CODE_WITHHOLDING_RECEIVABLE, 'Withholding Receivable', 'Asset');
        $receivableAccount = Account::getSystemAccount(Account::CODE_AR, 'Accounts Receivable', 'Asset');

        $this->assertDatabaseHas('journal_items', ['journal_entry_id' => $entry->id, 'account_id' => $bankAccount->id, 'debit' => 980.00]);
        $this->assertDatabaseHas('journal_items', ['journal_entry_id' => $entry->id, 'account_id' => $withholdingAccount->id, 'debit' => 20.00]);
        $this->assertDatabaseHas('journal_items', ['journal_entry_id' => $entry->id, 'account_id' => $receivableAccount->id, 'credit' => 1000.00]);

        $this->assertSame(0.0, $jobOrder->fresh()->balance);
        $this->assertSame(980.0, (float) $bank->fresh()->current_balance);
        $this->assertSame(980.0, (float) BankTransaction::where('source_type', 'payment')->where('source_id', $payment->id)->firstOrFail()->amount);
    }

    public function test_employee_loan_disbursement_and_repayment_use_staff_receivable()
    {
        $loanAccount = Account::getSystemAccount('1230', 'Employee Loans Receivable', 'Asset');
        $cashAccount = Account::getSystemAccount('1000', 'Cash in Hand', 'Asset');

        $disbursement = Payment::create([
            'payment_date' => now(),
            'amount' => 700.00,
            'method' => 'cash',
            'transaction_type' => 'employee_loan_disbursement',
            'reference' => 'Staff loan out',
        ]);
        $repayment = Payment::create([
            'payment_date' => now(),
            'amount' => 300.00,
            'method' => 'cash',
            'transaction_type' => 'employee_loan_repayment',
            'reference' => 'Staff loan in',
        ]);

        $disbursementEntry = JournalEntry::where('source_type', Payment::class)->where('source_id', $disbursement->id)->firstOrFail();
        $repaymentEntry = JournalEntry::where('source_type', Payment::class)->where('source_id', $repayment->id)->firstOrFail();

        $this->assertDatabaseHas('journal_items', ['journal_entry_id' => $disbursementEntry->id, 'account_id' => $loanAccount->id, 'debit' => 700.00]);
        $this->assertDatabaseHas('journal_items', ['journal_entry_id' => $disbursementEntry->id, 'account_id' => $cashAccount->id, 'credit' => 700.00]);
        $this->assertDatabaseHas('journal_items', ['journal_entry_id' => $repaymentEntry->id, 'account_id' => $cashAccount->id, 'debit' => 300.00]);
        $this->assertDatabaseHas('journal_items', ['journal_entry_id' => $repaymentEntry->id, 'account_id' => $loanAccount->id, 'credit' => 300.00]);
    }

    public function test_direct_expense_payment_posts_to_expense_account()
    {
        $cashAccount = Account::getSystemAccount('1000', 'Cash in Hand', 'Asset');
        $expenseAccount = Account::getSystemAccount('5990', 'Miscellaneous Expense', 'Expense');

        $payment = Payment::create([
            'payment_number' => 'PAY-002',
            'payment_date' => now(),
            'amount' => 1250.00,
            'transaction_type' => 'direct_expense',
            'method' => 'cash',
            'reference' => 'Electricity Bill',
        ]);

        $journalEntry = JournalEntry::where('source_type', Payment::class)
            ->where('source_id', $payment->id)
            ->first();

        $this->assertNotNull($journalEntry);
        $this->assertDatabaseHas('journal_items', [
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $expenseAccount->id,
            'debit' => 1250.00,
        ]);
        $this->assertDatabaseHas('journal_items', [
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $cashAccount->id,
            'credit' => 1250.00,
        ]);
    }

    public function test_payment_void_creates_reversal_journal_entry()
    {
        $customer = Partner::create([
            'name' => 'Void Test Customer',
            'is_customer' => true,
        ]);

        $payment = Payment::create([
            'payment_number' => 'PAY-003',
            'partner_id' => $customer->id,
            'payment_date' => now(),
            'amount' => 900.00,
            'direction' => 'inbound',
            'method' => 'cash',
            'reference' => 'Initial receipt',
        ]);

        $originalJournal = JournalEntry::where('source_type', Payment::class)
            ->where('source_id', $payment->id)
            ->where('status', 'posted')
            ->first();

        $this->assertNotNull($originalJournal);

        $reversalJournal = app(VoidPaymentJournalEntry::class)->handle($payment, 'Customer refund', null);

        $this->assertNotNull($reversalJournal);
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
        ]);
        $this->assertNotNull($payment->fresh()->voided_at);

        $this->assertDatabaseHas('journal_entries', [
            'id' => $originalJournal->id,
            'status' => 'void',
        ]);

        $this->assertDatabaseHas('journal_entries', [
            'id' => $reversalJournal->id,
            'reversal_of_journal_entry_id' => $originalJournal->id,
        ]);

        $this->assertDatabaseHas('journal_items', [
            'journal_entry_id' => $reversalJournal->id,
            'debit' => 0.00,
            'credit' => 900.00,
        ]);

        $this->assertDatabaseHas('journal_items', [
            'journal_entry_id' => $reversalJournal->id,
            'debit' => 900.00,
            'credit' => 0.00,
        ]);
    }
}

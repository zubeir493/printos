<?php

use App\Models\Account;
use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Setting;
use App\Models\Warehouse;
use App\Services\Accounting\VoidPaymentJournalEntry;
use App\Services\InvoiceGeneratorService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Fix #6 — CreateSalesJournalEntry now posts VAT to a Tax Payable account
// ---------------------------------------------------------------------------

test('completing a sales order with VAT posts revenue and tax payable separately', function () {
    Setting::create([
        'vat_enabled' => true,
        'vat_rate' => 15.00,
        'tax_configuration' => [['name' => 'VAT', 'rate' => 0.15]],
        'invoice_due_days' => 30,
        'currency_code' => 'ETB',
        'currency_symbol' => 'Birr',
    ]);

    $warehouse = Warehouse::create(['name' => 'Main', 'code' => 'MAIN']);
    $customer = Partner::create(['name' => 'VAT Customer', 'is_customer' => true]);
    $item = InventoryItem::create([
        'name' => 'Box', 'sku' => 'BOX-01', 'unit' => 'pcs',
        'purchase_unit' => 'pcs', 'conversion_factor' => 1,
        'type' => 'finished_good', 'is_sellable' => true,
        'price' => 100.00, 'average_cost' => 80.00,
    ]);
    InventoryBalance::create(['inventory_item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity_on_hand' => 50]);

    $order = SalesOrder::create([
        'warehouse_id' => $warehouse->id,
        'partner_id' => $customer->id,
        'order_date' => now(),
        'payment_mode' => 'credit',
        'status' => 'draft',
        'subtotal' => 1000.00,
        'tax_amount' => 150.00,
        'total' => 1150.00,
    ]);
    SalesOrderItem::create(['sales_order_id' => $order->id, 'inventory_item_id' => $item->id, 'quantity' => 10, 'unit_price' => 100.00, 'total' => 1000.00]);

    $order->update(['status' => 'submitted']);

    $entry = JournalEntry::where('source_type', SalesOrder::class)->where('source_id', $order->id)->first();
    expect($entry)->not->toBeNull();
    expect((float) $entry->total_debit)->toBe(1150.0);

    $revenueAccount = Account::getSystemAccount('4000', 'Sales Revenue', 'Revenue');
    $taxPayableAccount = Account::getSystemAccount('2100', 'VAT Payable', 'Liability');

    // Revenue line should be subtotal only (1000), not the full 1150
    $this->assertDatabaseHas('journal_items', [
        'journal_entry_id' => $entry->id,
        'account_id' => $revenueAccount->id,
        'credit' => 1000.00,
    ]);

    // Tax payable line should exist for the VAT portion
    $this->assertDatabaseHas('journal_items', [
        'journal_entry_id' => $entry->id,
        'account_id' => $taxPayableAccount->id,
        'credit' => 150.00,
    ]);
});

// ---------------------------------------------------------------------------
// Fix #10 — generateFromSalesOrder is idempotent (no duplicate invoices)
// ---------------------------------------------------------------------------

test('generating an invoice twice for the same sales order returns the existing invoice', function () {
    $warehouse = Warehouse::create(['name' => 'Inv Warehouse', 'code' => 'INV']);
    $customer = Partner::create(['name' => 'Invoice Customer', 'is_customer' => true]);
    $item = InventoryItem::create([
        'name' => 'Label', 'sku' => 'LBL-01', 'unit' => 'pcs',
        'purchase_unit' => 'pcs', 'conversion_factor' => 1,
        'type' => 'finished_good', 'is_sellable' => true,
        'price' => 10.00, 'average_cost' => 8.00,
    ]);
    InventoryBalance::create(['inventory_item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity_on_hand' => 100]);

    $order = SalesOrder::create([
        'warehouse_id' => $warehouse->id,
        'partner_id' => $customer->id,
        'order_date' => now(),
        'payment_mode' => 'credit',
        'status' => 'submitted',
        'subtotal' => 500.00,
        'tax_amount' => 0.00,
        'total' => 500.00,
    ]);

    // Pre-seed one invoice record
    Invoice::create([
        'invoice_number' => 'INV-2026-000001',
        'invoice_type' => 'sales',
        'order_id' => $order->id,
        'order_type' => 'sales_order',
        'partner_id' => $customer->id,
        'invoice_date' => now(),
        'due_date' => now()->addDays(30),
        'subtotal' => 500.00,
        'tax_amount' => 0.00,
        'total_amount' => 500.00,
        'balance_due' => 500.00,
        'status' => 'unpaid',
        'filename' => 'invoice-INV-2026-000001.pdf',
        'file_path' => 'invoices/invoice-INV-2026-000001.pdf',
    ]);

    // Calling the service again must NOT create a second invoice
    $service = app(InvoiceGeneratorService::class);
    $result = $service->generateFromSalesOrder($order);

    expect(Invoice::where('order_id', $order->id)->where('order_type', 'sales_order')->count())->toBe(1);
    expect($result['invoice']->invoice_number)->toBe('INV-2026-000001');
});

// ---------------------------------------------------------------------------
// Fix #19 - Voiding a direct payment updates balances
// ---------------------------------------------------------------------------

test('voiding a direct payment restores order balance', function () {
    $customer = Partner::create(['name' => 'Void Customer', 'is_customer' => true]);

    $warehouse = Warehouse::create(['name' => 'Void WH', 'code' => 'VWH']);
    $order = SalesOrder::create([
        'warehouse_id' => $warehouse->id,
        'partner_id' => $customer->id,
        'order_date' => now(),
        'payment_mode' => 'credit',
        'status' => 'submitted',
        'subtotal' => 500.00,
        'tax_amount' => 0.00,
        'total' => 500.00,
    ]);

    $payment = Payment::create([
        'payment_number' => 'PAY-VOID-001',
        'partner_id' => $customer->id,
        'payment_date' => now(),
        'amount' => 500.00,
        'direction' => 'inbound',
        'transaction_type' => 'customer_receipt',
        'method' => 'cash',
        'payable_type' => SalesOrder::class,
        'payable_id' => $order->id,
    ]);

    expect($order->fresh()->paid_amount)->toBe(500.0);

    app(VoidPaymentJournalEntry::class)->handle($payment, 'Test void');

    // Order balance must be restored to full amount
    expect($order->fresh()->balance)->toBe(500.0);

    // Payment must be marked voided
    expect($payment->fresh()->voided_at)->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Fix #3 — UpdateOverdueInvoices marks unpaid/partial invoices, not just sent
// ---------------------------------------------------------------------------

test('update overdue invoices command marks unpaid and partial invoices as overdue', function () {
    $partner = Partner::create(['name' => 'Overdue Partner', 'is_customer' => true]);

    $unpaidInvoice = Invoice::create([
        'invoice_number' => 'INV-OD-001',
        'invoice_type' => 'sales',
        'order_id' => 1,
        'order_type' => 'sales_order',
        'partner_id' => $partner->id,
        'invoice_date' => now()->subDays(60),
        'due_date' => now()->subDays(30),
        'subtotal' => 1000.00,
        'tax_amount' => 0.00,
        'total_amount' => 1000.00,
        'balance_due' => 1000.00,
        'status' => 'unpaid',
        'filename' => 'inv-od-001.pdf',
        'file_path' => 'invoices/inv-od-001.pdf',
    ]);

    $partialInvoice = Invoice::create([
        'invoice_number' => 'INV-OD-002',
        'invoice_type' => 'sales',
        'order_id' => 2,
        'order_type' => 'sales_order',
        'partner_id' => $partner->id,
        'invoice_date' => now()->subDays(60),
        'due_date' => now()->subDays(10),
        'subtotal' => 2000.00,
        'tax_amount' => 0.00,
        'total_amount' => 2000.00,
        'balance_due' => 1000.00,
        'status' => 'partial',
        'filename' => 'inv-od-002.pdf',
        'file_path' => 'invoices/inv-od-002.pdf',
    ]);

    $this->artisan('invoices:update-overdue')->assertExitCode(0);

    expect($unpaidInvoice->fresh()->status)->toBe('overdue');
    expect($partialInvoice->fresh()->status)->toBe('overdue');
});

// ---------------------------------------------------------------------------
// Fix #22 — inventory_balances unique constraint prevents duplicate rows
// ---------------------------------------------------------------------------

test('inventory balance unique constraint prevents duplicate item-warehouse rows', function () {
    $item = InventoryItem::create([
        'name' => 'Paper', 'sku' => 'PPR-01', 'unit' => 'ream',
        'purchase_unit' => 'ream', 'conversion_factor' => 1,
        'type' => 'raw_material', 'is_sellable' => false,
        'price' => 5.00, 'average_cost' => 5.00,
    ]);
    $warehouse = Warehouse::create(['name' => 'Unique WH', 'code' => 'UWH']);

    InventoryBalance::create(['inventory_item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity_on_hand' => 100]);

    expect(fn () => InventoryBalance::create([
        'inventory_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity_on_hand' => 50,
    ]))->toThrow(QueryException::class);
});

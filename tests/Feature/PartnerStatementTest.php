<?php

use App\Filament\Resources\Partners\Pages\PartnerStatement;
use App\Models\Invoice;
use App\Models\JobOrder;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Accounting\VoidPaymentJournalEntry;
use App\UserRole;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('summarizes a customer only partner statement', function (): void {
    $partner = Partner::factory()->create([
        'is_customer' => true,
        'is_supplier' => false,
    ]);

    Invoice::create([
        'invoice_number' => 'INV-CUST-001',
        'invoice_type' => 'sales',
        'order_type' => 'sales_order',
        'partner_id' => $partner->id,
        'invoice_date' => now()->subDays(2),
        'due_date' => now()->addDays(28),
        'subtotal' => 1000,
        'tax_amount' => 0,
        'total_amount' => 1000,
        'balance_due' => 600,
        'status' => 'partial',
        'filename' => 'invoice.pdf',
        'file_path' => 'invoices/invoice.pdf',
    ]);

    Payment::factory()->create([
        'partner_id' => $partner->id,
        'payment_date' => now()->subDay(),
        'amount' => 400,
        'direction' => 'inbound',
        'method' => 'bank',
    ]);

    Payment::factory()->create([
        'partner_id' => $partner->id,
        'payment_date' => now(),
        'amount' => 999,
        'direction' => 'inbound',
        'method' => 'cash',
        'voided_at' => now(),
    ]);

    $summary = $partner->statementSummary();

    expect($summary['receivable_total'])->toBe(600.0)
        ->and($summary['payable_total'])->toBe(0.0)
        ->and($summary['inbound_payments_total'])->toBe(400.0)
        ->and($summary['net_balance'])->toBe(600.0);

    $rows = $partner->statementRows();

    expect($rows)->toHaveCount(3)
        ->and($rows->first()['status'])->toBe('voided')
        ->and($rows->first()['balance_impact'])->toBe(0.0);
});

it('does not count paid invoices as open receivables even if balance due is stale', function (): void {
    $partner = Partner::factory()->create([
        'is_customer' => true,
        'is_supplier' => false,
    ]);

    Invoice::create([
        'invoice_number' => 'INV-PAID-STALE',
        'invoice_type' => 'sales',
        'order_type' => 'sales_order',
        'partner_id' => $partner->id,
        'invoice_date' => now()->subDay(),
        'due_date' => now()->addDays(29),
        'subtotal' => 1000,
        'tax_amount' => 0,
        'total_amount' => 1000,
        'balance_due' => 1000,
        'status' => 'paid',
        'filename' => 'paid.pdf',
        'file_path' => 'invoices/paid.pdf',
    ]);

    $summary = $partner->statementSummary();
    $row = $partner->statementRows()->firstWhere('reference', 'INV-PAID-STALE');

    expect($summary['receivable_total'])->toBe(0.0)
        ->and($summary['net_balance'])->toBe(0.0)
        ->and($row['description'])->toBe('Standalone invoice settled')
        ->and($row['balance_impact'])->toBe(0.0);
});

it('uses sales order payment status as the receivable source of truth', function (): void {
    $partner = Partner::factory()->create([
        'is_customer' => true,
    ]);

    $warehouse = Warehouse::factory()->create();

    $salesOrder = SalesOrder::create([
        'order_number' => 'SO-STMT-001',
        'warehouse_id' => $warehouse->id,
        'partner_id' => $partner->id,
        'order_date' => now()->subDays(2),
        'due_date' => now()->addDays(10),
        'payment_mode' => 'credit',
        'subtotal' => 1000,
        'tax_amount' => 0,
        'total' => 1000,
        'status' => SalesOrder::STATUS_SUBMITTED,
    ]);

    Invoice::create([
        'invoice_number' => 'INV-STMT-ORDER',
        'invoice_type' => 'sales',
        'order_type' => 'sales_order',
        'order_id' => $salesOrder->id,
        'partner_id' => $partner->id,
        'invoice_date' => now()->subDay(),
        'due_date' => now()->addDays(29),
        'subtotal' => 1000,
        'tax_amount' => 0,
        'total_amount' => 1000,
        'balance_due' => 1000,
        'status' => 'sent',
        'filename' => 'linked.pdf',
        'file_path' => 'invoices/linked.pdf',
    ]);

    Payment::factory()->create([
        'partner_id' => $partner->id,
        'payable_type' => SalesOrder::class,
        'payable_id' => $salesOrder->id,
        'payment_date' => now(),
        'amount' => 1000,
        'direction' => 'inbound',
        'method' => 'bank',
    ]);

    $summary = $partner->statementSummary();
    $salesOrderRow = $partner->statementRows()->firstWhere('reference', 'SO-STMT-001');
    $invoiceRow = $partner->statementRows()->firstWhere('reference', 'INV-STMT-ORDER');

    expect($summary['receivable_total'])->toBe(0.0)
        ->and($salesOrderRow['open_balance'])->toBe(0.0)
        ->and($invoiceRow['open_balance'])->toBe(0.0)
        ->and($invoiceRow['description'])->toBe('Invoice issued for related document');
});

it('syncs related invoices when document payments are created and voided', function (): void {
    $partner = Partner::factory()->create([
        'is_customer' => true,
    ]);

    $warehouse = Warehouse::factory()->create();

    $salesOrder = SalesOrder::create([
        'order_number' => 'SO-SYNC-001',
        'warehouse_id' => $warehouse->id,
        'partner_id' => $partner->id,
        'order_date' => now()->subDays(2),
        'due_date' => now()->addDays(10),
        'payment_mode' => 'credit',
        'subtotal' => 1000,
        'tax_amount' => 0,
        'total' => 1000,
        'status' => SalesOrder::STATUS_SUBMITTED,
    ]);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-SYNC-001',
        'invoice_type' => 'sales',
        'order_type' => 'sales_order',
        'order_id' => $salesOrder->id,
        'partner_id' => $partner->id,
        'invoice_date' => now()->subDay(),
        'due_date' => now()->addDays(29),
        'subtotal' => 1000,
        'tax_amount' => 0,
        'total_amount' => 1000,
        'balance_due' => 1000,
        'status' => 'sent',
        'filename' => 'sync.pdf',
        'file_path' => 'invoices/sync.pdf',
    ]);

    $payment = Payment::factory()->create([
        'partner_id' => $partner->id,
        'payable_type' => SalesOrder::class,
        'payable_id' => $salesOrder->id,
        'payment_date' => now(),
        'amount' => 1000,
        'direction' => 'inbound',
        'method' => 'bank',
    ]);

    expect($invoice->refresh()->balance_due)->toBe('0.00')
        ->and($invoice->status)->toBe('paid');

    app(VoidPaymentJournalEntry::class)->handle($payment, 'Statement sync regression');

    expect($invoice->refresh()->balance_due)->toBe('1000.00')
        ->and($invoice->status)->toBe('sent');
});

it('uses job orders and purchase orders as statement balance sources', function (): void {
    $partner = Partner::factory()->create([
        'is_customer' => true,
        'is_supplier' => true,
    ]);

    $jobOrder = JobOrder::factory()->create([
        'partner_id' => $partner->id,
        'job_order_number' => 'JO-STMT-001',
        'submission_date' => now()->subDays(3),
        'subtotal' => 500,
        'tax_amount' => 0,
        'total' => 500,
        'status' => 'active',
    ]);

    $purchaseOrder = PurchaseOrder::factory()->create([
        'partner_id' => $partner->id,
        'po_number' => 'PO-STMT-001',
        'order_date' => now()->subDays(2),
        'subtotal' => 300,
        'tax_amount' => 0,
        'total' => 300,
        'status' => 'approved',
    ]);

    Payment::factory()->create([
        'partner_id' => $partner->id,
        'payable_type' => JobOrder::class,
        'payable_id' => $jobOrder->id,
        'amount' => 200,
        'direction' => 'inbound',
        'method' => 'cash',
    ]);

    Payment::factory()->create([
        'partner_id' => $partner->id,
        'payable_type' => PurchaseOrder::class,
        'payable_id' => $purchaseOrder->id,
        'amount' => 100,
        'direction' => 'outbound',
        'method' => 'cash',
    ]);

    $summary = $partner->statementSummary();

    expect($summary['receivable_total'])->toBe(300.0)
        ->and($summary['payable_total'])->toBe(200.0)
        ->and($summary['net_balance'])->toBe(100.0)
        ->and($partner->statementRows()->firstWhere('reference', 'JO-STMT-001')['open_balance'])->toBe(300.0)
        ->and($partner->statementRows()->firstWhere('reference', 'PO-STMT-001')['open_balance'])->toBe(200.0);
});

it('summarizes a supplier only partner statement', function (): void {
    $partner = Partner::factory()->create([
        'is_customer' => false,
        'is_supplier' => true,
    ]);

    Invoice::create([
        'invoice_number' => 'PI-SUP-001',
        'invoice_type' => 'purchase',
        'order_type' => 'purchase_order',
        'partner_id' => $partner->id,
        'invoice_date' => now()->subDays(3),
        'due_date' => now()->addDays(7),
        'subtotal' => 500,
        'tax_amount' => 0,
        'total_amount' => 500,
        'balance_due' => 200,
        'status' => 'partial',
        'filename' => 'purchase-invoice.pdf',
        'file_path' => 'invoices/purchase-invoice.pdf',
    ]);

    Payment::factory()->create([
        'partner_id' => $partner->id,
        'payment_date' => now()->subDay(),
        'amount' => 300,
        'direction' => 'outbound',
        'method' => 'cheque',
    ]);

    $summary = $partner->statementSummary();

    expect($summary['receivable_total'])->toBe(0.0)
        ->and($summary['payable_total'])->toBe(200.0)
        ->and($summary['outbound_payments_total'])->toBe(300.0)
        ->and($summary['net_balance'])->toBe(-200.0);
});

it('summarizes a partner that is both customer and supplier', function (): void {
    $partner = Partner::factory()->create([
        'is_customer' => true,
        'is_supplier' => true,
    ]);

    Invoice::create([
        'invoice_number' => 'INV-BOTH-001',
        'invoice_type' => 'sales',
        'order_type' => 'sales_order',
        'partner_id' => $partner->id,
        'invoice_date' => now()->subDays(4),
        'due_date' => now()->addDays(10),
        'subtotal' => 900,
        'tax_amount' => 0,
        'total_amount' => 900,
        'balance_due' => 900,
        'status' => 'sent',
        'filename' => 'sales.pdf',
        'file_path' => 'invoices/sales.pdf',
    ]);

    Invoice::create([
        'invoice_number' => 'PI-BOTH-001',
        'invoice_type' => 'purchase',
        'order_type' => 'purchase_order',
        'partner_id' => $partner->id,
        'invoice_date' => now()->subDays(2),
        'due_date' => now()->addDays(14),
        'subtotal' => 250,
        'tax_amount' => 0,
        'total_amount' => 250,
        'balance_due' => 150,
        'status' => 'partial',
        'filename' => 'purchase.pdf',
        'file_path' => 'invoices/purchase.pdf',
    ]);

    $summary = $partner->statementSummary();

    expect($summary['receivable_total'])->toBe(900.0)
        ->and($summary['payable_total'])->toBe(150.0)
        ->and($summary['net_balance'])->toBe(750.0);
});

it('renders the partner statement page', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Admin,
    ]));

    $partner = Partner::factory()->create([
        'name' => 'Statement Partner',
        'is_customer' => true,
    ]);

    Invoice::create([
        'invoice_number' => 'INV-PAGE-001',
        'invoice_type' => 'sales',
        'order_type' => 'sales_order',
        'partner_id' => $partner->id,
        'invoice_date' => now(),
        'due_date' => now()->addDays(30),
        'subtotal' => 100,
        'tax_amount' => 0,
        'total_amount' => 100,
        'balance_due' => 100,
        'status' => 'sent',
        'filename' => 'statement.pdf',
        'file_path' => 'invoices/statement.pdf',
    ]);

    Livewire::test(PartnerStatement::class, ['record' => $partner->id])
        ->assertOk()
        ->assertSee('Receivables Outstanding')
        ->assertSee('Total')
        ->assertSee('INV-PAGE-001');
});

it('shows document balances by default and payment history only when requested', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Admin,
    ]));

    $partner = Partner::factory()->create([
        'name' => 'Payment Filter Partner',
        'is_customer' => true,
    ]);

    $warehouse = Warehouse::factory()->create();

    $salesOrder = SalesOrder::create([
        'order_number' => 'SO-FILTER-001',
        'warehouse_id' => $warehouse->id,
        'partner_id' => $partner->id,
        'order_date' => now(),
        'payment_mode' => 'credit',
        'subtotal' => 1000,
        'tax_amount' => 0,
        'total' => 1000,
        'status' => SalesOrder::STATUS_SUBMITTED,
    ]);

    Payment::factory()->create([
        'payment_number' => 'PAY-FILTER-001',
        'partner_id' => $partner->id,
        'payable_type' => SalesOrder::class,
        'payable_id' => $salesOrder->id,
        'payment_date' => now(),
        'amount' => 250,
        'direction' => 'inbound',
        'method' => 'bank',
    ]);

    Livewire::test(PartnerStatement::class, ['record' => $partner->id])
        ->assertOk()
        ->assertSee('SO-FILTER-001')
        ->assertDontSee('PAY-FILTER-001')
        ->filterTable('activity', 'payment')
        ->assertSee('PAY-FILTER-001');
});

<?php

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\JobOrder;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use App\Observers\PaymentAllocationObserver;
use App\Services\InvoiceGeneratorService;
use App\UserRole;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Fix #1 — Payment model auto-generates payment_number via booted()
// ---------------------------------------------------------------------------

test('payment number is auto-generated when not provided', function () {
    $partner = Partner::create(['name' => 'Auto Number Customer', 'is_customer' => true]);

    $payment = Payment::create([
        'partner_id' => $partner->id,
        'payment_date' => now(),
        'amount' => 100.00,
        'direction' => 'inbound',
        'transaction_type' => 'customer_receipt',
        'method' => 'cash',
    ]);

    expect($payment->payment_number)->toMatch('/^PAY-\d+$/');
});

test('two payments created concurrently get unique payment numbers', function () {
    $partner = Partner::create(['name' => 'Concurrent Customer', 'is_customer' => true]);

    $p1 = Payment::create([
        'partner_id' => $partner->id,
        'payment_date' => now(),
        'amount' => 100.00,
        'direction' => 'inbound',
        'transaction_type' => 'customer_receipt',
        'method' => 'cash',
    ]);

    $p2 = Payment::create([
        'partner_id' => $partner->id,
        'payment_date' => now(),
        'amount' => 200.00,
        'direction' => 'inbound',
        'transaction_type' => 'customer_receipt',
        'method' => 'cash',
    ]);

    expect($p1->payment_number)->not->toBe($p2->payment_number);
});

// ---------------------------------------------------------------------------
// Fix #1 — JobOrder number auto-generated via observer
// ---------------------------------------------------------------------------

test('job order number is auto-generated when not provided', function () {
    $jo = JobOrder::factory()->create(['job_order_number' => null]);

    expect($jo->job_order_number)->toMatch('/^JO-\d{4}$/');
});

test('two job orders get unique sequential numbers', function () {
    $jo1 = JobOrder::factory()->create(['job_order_number' => null]);
    $jo2 = JobOrder::factory()->create(['job_order_number' => null]);

    expect($jo1->job_order_number)->not->toBe($jo2->job_order_number);
});

// ---------------------------------------------------------------------------
// Fix #7 — GoodsReceiptObserver uses lockForUpdate to prevent double-posting
// ---------------------------------------------------------------------------

test('goods receipt posted_at is stamped after posting and stock is received once', function () {
    $supplier = Partner::create(['name' => 'GR Supplier', 'is_supplier' => true]);
    $warehouse = Warehouse::create(['name' => 'GR Warehouse', 'code' => 'GRW']);
    $item = InventoryItem::create([
        'name' => 'Paper Ream', 'sku' => 'PR-01', 'unit' => 'ream',
        'purchase_unit' => 'ream', 'conversion_factor' => 1,
        'type' => 'raw_material', 'is_sellable' => false,
        'price' => 5.00, 'average_cost' => 5.00,
    ]);

    $po = PurchaseOrder::create([
        'po_number' => 'PO-GR-001',
        'partner_id' => $supplier->id,
        'order_date' => now(),
        'status' => 'approved',
        'subtotal' => 500.00,
        'total' => 500.00,
    ]);

    $poItem = PurchaseOrderItem::create([
        'purchase_order_id' => $po->id,
        'inventory_item_id' => $item->id,
        'quantity' => 100,
        'unit_price' => 5.00,
        'total' => 500.00,
        'received_quantity' => 0,
    ]);

    $receipt = GoodsReceipt::create([
        'receipt_number' => 'GR-001',
        'purchase_order_id' => $po->id,
        'warehouse_id' => $warehouse->id,
        'receipt_date' => now(),
        'status' => 'draft',
    ]);

    GoodsReceiptItem::create([
        'goods_receipt_id' => $receipt->id,
        'purchase_order_item_id' => $poItem->id,
        'quantity_received' => 100,
    ]);

    // Post the receipt
    $receipt->update(['status' => 'posted']);

    $receipt->refresh();
    expect($receipt->posted_at)->not->toBeNull();

    // Stock should be received exactly once
    $this->assertDatabaseHas('inventory_balances', [
        'inventory_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity_on_hand' => 100.00,
    ]);

    // Posting again must not double-count
    $receipt->update(['status' => 'posted']);

    $this->assertDatabaseHas('inventory_balances', [
        'inventory_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity_on_hand' => 100.00,
    ]);
});

// ---------------------------------------------------------------------------
// Fix #13 — UserRole: non-admin roles cannot access the admin panel
// ---------------------------------------------------------------------------

test('non-admin roles cannot access the admin panel', function () {
    $panel = new class extends Panel
    {
        public function getId(): string
        {
            return 'admin';
        }
    };

    foreach (UserRole::cases() as $role) {
        if ($role === UserRole::Admin) {
            continue;
        }
        expect($role->canAccessPanel($panel))->toBeFalse(
            "Role {$role->value} should not access the admin panel"
        );
    }
});

test('admin role can access all panels', function () {
    $panelIds = ['admin', 'finance', 'sales', 'warehouse', 'design', 'production', 'hr', 'retail', 'operations'];

    foreach ($panelIds as $panelId) {
        $panel = new class($panelId) extends Panel
        {
            public function __construct(private string $panelId) {}

            public function getId(): string
            {
                return $this->panelId;
            }
        };

        expect(UserRole::Admin->canAccessPanel($panel))->toBeTrue(
            "Admin should access panel {$panelId}"
        );
    }
});

// ---------------------------------------------------------------------------
// Fix #14 — PaymentAllocationObserver uses total of all allocations for advance
// ---------------------------------------------------------------------------

test('job order advance_amount reflects total of all allocations not just the first', function () {
    $partner = Partner::create(['name' => 'Advance Customer', 'is_customer' => true]);

    $jo = JobOrder::factory()->create([
        'partner_id' => $partner->id,
        'subtotal' => 1000.00,
        'tax_amount' => 0.00,
        'total' => 1000.00,
    ]);

    $p1 = Payment::create([
        'partner_id' => $partner->id,
        'payment_date' => now(),
        'amount' => 300.00,
        'direction' => 'inbound',
        'transaction_type' => 'customer_receipt',
        'method' => 'cash',
    ]);

    $p2 = Payment::create([
        'partner_id' => $partner->id,
        'payment_date' => now(),
        'amount' => 200.00,
        'direction' => 'inbound',
        'transaction_type' => 'customer_receipt',
        'method' => 'cash',
    ]);

    PaymentAllocation::create([
        'payment_id' => $p1->id,
        'allocatable_type' => JobOrder::class,
        'allocatable_id' => $jo->id,
        'allocated_amount' => 300.00,
    ]);

    PaymentAllocation::create([
        'payment_id' => $p2->id,
        'allocatable_type' => JobOrder::class,
        'allocatable_id' => $jo->id,
        'allocated_amount' => 200.00,
    ]);

    $jo->refresh();
    expect($jo->advance_paid)->toBeTrue();
    // Should be 500 (sum of both), not 300 (just the first)
    expect((float) $jo->advance_amount)->toBe(500.0);
});

test('job order advance_amount updates correctly when an allocation is deleted', function () {
    $partner = Partner::create(['name' => 'Advance Delete Customer', 'is_customer' => true]);

    $jo = JobOrder::factory()->create([
        'partner_id' => $partner->id,
        'subtotal' => 1000.00,
        'tax_amount' => 0.00,
        'total' => 1000.00,
    ]);

    $p1 = Payment::create([
        'partner_id' => $partner->id,
        'payment_date' => now(),
        'amount' => 300.00,
        'direction' => 'inbound',
        'transaction_type' => 'customer_receipt',
        'method' => 'cash',
    ]);

    $p2 = Payment::create([
        'partner_id' => $partner->id,
        'payment_date' => now(),
        'amount' => 200.00,
        'direction' => 'inbound',
        'transaction_type' => 'customer_receipt',
        'method' => 'cash',
    ]);

    $alloc1 = PaymentAllocation::create([
        'payment_id' => $p1->id,
        'allocatable_type' => JobOrder::class,
        'allocatable_id' => $jo->id,
        'allocated_amount' => 300.00,
    ]);

    PaymentAllocation::create([
        'payment_id' => $p2->id,
        'allocatable_type' => JobOrder::class,
        'allocatable_id' => $jo->id,
        'allocated_amount' => 200.00,
    ]);

    // Delete the first allocation — advance_amount should drop to 200, not 0
    $alloc1->deleteQuietly();
    app(PaymentAllocationObserver::class)->deleted($alloc1);

    $jo->refresh();
    expect((float) $jo->advance_amount)->toBe(200.0);
    expect($jo->advance_paid)->toBeTrue();
});

// ---------------------------------------------------------------------------
// Fix #17 — Over-allocation guard on PaymentAllocation
// ---------------------------------------------------------------------------

test('cannot allocate more than the payment amount', function () {
    $partner = Partner::create(['name' => 'Over-alloc Customer', 'is_customer' => true]);

    $payment = Payment::create([
        'partner_id' => $partner->id,
        'payment_date' => now(),
        'amount' => 500.00,
        'direction' => 'inbound',
        'transaction_type' => 'customer_receipt',
        'method' => 'cash',
    ]);

    $warehouse = Warehouse::create(['name' => 'OA Warehouse', 'code' => 'OAW']);
    $order1 = SalesOrder::create([
        'warehouse_id' => $warehouse->id,
        'partner_id' => $partner->id,
        'order_date' => now(),
        'payment_mode' => 'credit',
        'status' => 'completed',
        'subtotal' => 400.00,
        'tax_amount' => 0.00,
        'total' => 400.00,
    ]);
    $order2 = SalesOrder::create([
        'warehouse_id' => $warehouse->id,
        'partner_id' => $partner->id,
        'order_date' => now(),
        'payment_mode' => 'credit',
        'status' => 'completed',
        'subtotal' => 300.00,
        'tax_amount' => 0.00,
        'total' => 300.00,
    ]);

    // First allocation of 400 — fine
    PaymentAllocation::create([
        'payment_id' => $payment->id,
        'allocatable_type' => SalesOrder::class,
        'allocatable_id' => $order1->id,
        'allocated_amount' => 400.00,
    ]);

    // Second allocation of 200 would bring total to 600 > 500 — must throw
    expect(fn () => PaymentAllocation::create([
        'payment_id' => $payment->id,
        'allocatable_type' => SalesOrder::class,
        'allocatable_id' => $order2->id,
        'allocated_amount' => 200.00,
    ]))->toThrow(RuntimeException::class);
});

// ---------------------------------------------------------------------------
// Fix #15 — Invoice sequence number extraction is correct (no off-by-one)
// ---------------------------------------------------------------------------

test('invoice getNextSequence returns correct next number after an existing invoice', function () {
    $partner = Partner::create(['name' => 'Seq Customer', 'is_customer' => true]);

    // Seed an invoice with a known sequence number via the service format
    Invoice::create([
        'invoice_number' => 'INV-2026-000005',
        'invoice_type' => 'sales',
        'order_id' => 1,
        'order_type' => 'sales_order',
        'partner_id' => $partner->id,
        'invoice_date' => now(),
        'due_date' => now()->addDays(30),
        'subtotal' => 100.00,
        'tax_amount' => 0.00,
        'total_amount' => 100.00,
        'balance_due' => 100.00,
        'status' => 'unpaid',
        'filename' => 'inv-seq-test.pdf',
        'file_path' => 'invoices/inv-seq-test.pdf',
    ]);

    // Call getNextSequence via reflection — avoids needing a real PDF renderer
    $service = app(InvoiceGeneratorService::class);
    $method = new ReflectionMethod($service, 'getNextSequence');
    $method->setAccessible(true);

    $next = $method->invoke($service, 'INV', '2026');

    // Must be 6, not 1 (off-by-one) or some garbled value
    expect($next)->toBe(6);
});

test('invoice getNextSequence starts at 1 when no prior invoices exist for that prefix+year', function () {
    $service = app(InvoiceGeneratorService::class);
    $method = new ReflectionMethod($service, 'getNextSequence');
    $method->setAccessible(true);

    $next = $method->invoke($service, 'INV', '2099');

    expect($next)->toBe(1);
});

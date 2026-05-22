<?php

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\JobOrder;
use App\Models\JobOrderTask;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\SalesOrder;
use App\Models\Warehouse;
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
// Fix #14 - Job order advance uses total direct payments
// ---------------------------------------------------------------------------

test('job order advance_amount reflects total of all direct payments', function () {
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
        'payable_type' => JobOrder::class,
        'payable_id' => $jo->id,
    ]);

    $p2 = Payment::create([
        'partner_id' => $partner->id,
        'payment_date' => now(),
        'amount' => 200.00,
        'direction' => 'inbound',
        'transaction_type' => 'customer_receipt',
        'method' => 'cash',
        'payable_type' => JobOrder::class,
        'payable_id' => $jo->id,
    ]);

    $jo->refresh();
    expect($jo->advance_paid)->toBeTrue();
    expect((float) $jo->advance_amount)->toBe(500.0);
});

test('job order advance_amount updates correctly when a direct payment is deleted', function () {
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
        'payable_type' => JobOrder::class,
        'payable_id' => $jo->id,
    ]);

    Payment::create([
        'partner_id' => $partner->id,
        'payment_date' => now(),
        'amount' => 200.00,
        'direction' => 'inbound',
        'transaction_type' => 'customer_receipt',
        'method' => 'cash',
        'payable_type' => JobOrder::class,
        'payable_id' => $jo->id,
    ]);

    $p1->delete();

    $jo->refresh();
    expect((float) $jo->advance_amount)->toBe(200.0);
    expect($jo->advance_paid)->toBeTrue();
});

test('job order completes when final payment is recorded after all tasks are completed', function () {
    $partner = Partner::create(['name' => 'Completion Customer', 'is_customer' => true]);

    $jo = JobOrder::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'active',
        'subtotal' => 1000.00,
        'tax_amount' => 0.00,
        'total' => 1000.00,
    ]);

    JobOrderTask::create([
        'job_order_id' => $jo->id,
        'name' => 'Print',
        'quantity' => 100,
        'task_cost' => 1000.00,
        'status' => 'completed',
    ]);

    $jo->refresh();

    Payment::create([
        'partner_id' => $partner->id,
        'payment_date' => now(),
        'amount' => $jo->total,
        'direction' => 'inbound',
        'transaction_type' => 'customer_receipt',
        'method' => 'cash',
        'payable_type' => JobOrder::class,
        'payable_id' => $jo->id,
    ]);

    expect((string) $jo->fresh()->status)->toBe('completed');
});

test('job order completes when final task is completed after payment is fully recorded', function () {
    $partner = Partner::create(['name' => 'Task Completion Customer', 'is_customer' => true]);

    $jo = JobOrder::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'active',
        'subtotal' => 1000.00,
        'tax_amount' => 0.00,
        'total' => 1000.00,
    ]);

    $task = JobOrderTask::create([
        'job_order_id' => $jo->id,
        'name' => 'Bind',
        'quantity' => 100,
        'task_cost' => 1000.00,
        'status' => 'production',
    ]);

    $jo->refresh();

    Payment::create([
        'partner_id' => $partner->id,
        'payment_date' => now(),
        'amount' => $jo->total,
        'direction' => 'inbound',
        'transaction_type' => 'customer_receipt',
        'method' => 'cash',
        'payable_type' => JobOrder::class,
        'payable_id' => $jo->id,
    ]);

    expect((string) $jo->fresh()->status)->toBe('active');

    $task->update(['status' => 'completed']);

    expect((string) $jo->fresh()->status)->toBe('completed');
});

// ---------------------------------------------------------------------------
// Fix #17 - Direct payment payable links
// ---------------------------------------------------------------------------

test('direct payment records the payable document it settles', function () {
    $partner = Partner::create(['name' => 'Over-alloc Customer', 'is_customer' => true]);

    $warehouse = Warehouse::create(['name' => 'OA Warehouse', 'code' => 'OAW']);
    $order = SalesOrder::create([
        'warehouse_id' => $warehouse->id,
        'partner_id' => $partner->id,
        'order_date' => now(),
        'payment_mode' => 'credit',
        'status' => 'submitted',
        'subtotal' => 400.00,
        'tax_amount' => 0.00,
        'total' => 400.00,
    ]);

    Payment::create([
        'partner_id' => $partner->id,
        'payment_date' => now(),
        'amount' => 400.00,
        'direction' => 'inbound',
        'transaction_type' => 'customer_receipt',
        'method' => 'cash',
        'payable_type' => SalesOrder::class,
        'payable_id' => $order->id,
    ]);

    expect((float) $order->fresh()->paid_amount)->toBe(400.0)
        ->and((float) $order->fresh()->balance)->toBe(0.0);
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

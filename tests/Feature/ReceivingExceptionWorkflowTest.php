<?php

use App\Models\GoodsReceipt;
use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\JournalEntry;
use App\Models\MaintenanceStoreEntry;
use App\Models\Partner;
use App\Models\PurchaseOrder;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\MaintenanceStore;
use App\Services\PostDirectGoodsReceipt;
use App\Support\PrivateStorage;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Notification::fake();
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
});

/** @return array<string, mixed> */
function exceptionReceiptInput(): array
{
    return [
        'source' => 'supplier',
        'supplier_id' => Partner::factory()->create(['is_supplier' => true])->id,
        'source_reference' => 'DN-101',
        'warehouse_id' => Warehouse::factory()->create()->id,
        'receipt_date' => today()->toDateString(),
        'items' => [['inventory_item_id' => InventoryItem::factory()->create(['average_cost' => 10])->id, 'quantity_received' => 10]],
    ];
}

it('closes a partial PO at received value and preserves the ordered amount', function (): void {
    $supplier = Partner::factory()->create(['is_supplier' => true]);
    $order = PurchaseOrder::create(['partner_id' => $supplier->id, 'order_date' => today(), 'status' => 'approved']);
    $item = $order->purchaseOrderItems()->create([
        'inventory_item_id' => InventoryItem::factory()->create(['average_cost' => 0])->id,
        'quantity' => 10, 'unit_price' => 100, 'total' => 1000,
    ]);
    $order->update(['subtotal' => 1000, 'tax_amount' => 150, 'total' => 1150]);
    $receipt = $order->goodsReceipts()->create([
        'warehouse_id' => Warehouse::factory()->create()->id, 'receipt_date' => today(), 'status' => 'draft',
    ]);
    $receipt->items()->create(['purchase_order_item_id' => $item->id, 'quantity_received' => 4]);
    $receipt->update(['status' => 'posted']);

    expect(fn () => $order->update(['status' => 'cancelled']))->toThrow(ValidationException::class);

    $order->refresh()->closeRemainingQuantity();
    $order->refresh();
    $journal = JournalEntry::where('source_type', PurchaseOrder::class)->where('source_id', $order->id)->sole();
    expect($order->status)->toBe('received')
        ->and((float) $order->total)->toBe(1150.0)
        ->and($order->balance)->toBe(460.0)
        ->and((float) $journal->total_credit)->toBe(460.0)
        ->and((float) InventoryBalance::sole()->quantity_on_hand)->toBe(4.0);
});

it('blocks duplicate receipts and reverses a receipt exactly once without changing item cost', function (): void {
    $input = exceptionReceiptInput();
    $service = app(PostDirectGoodsReceipt::class);
    $receipt = $service->handle($input);
    expect(fn () => $service->handle([...$input, 'source_reference' => ' dn-101 ']))->toThrow(ValidationException::class);
    $service->reverse($receipt, 'Wrong item delivered');
    expect(fn () => $service->reverse($receipt, 'Duplicate reversal'))->toThrow(ValidationException::class)
        ->and((float) InventoryBalance::sole()->quantity_on_hand)->toBe(0.0)
        ->and(StockMovement::where('type', GoodsReceipt::REVERSAL_MOVEMENT)->count())->toBe(1)
        ->and($receipt->refresh()->reversal_reason)->toBe('Wrong item delivered')
        ->and((float) InventoryItem::sole()->average_cost)->toBe(10.0);
    $service->handle($input);
    expect((float) InventoryBalance::sole()->quantity_on_hand)->toBe(10.0);
});

it('lets Finance match a supplier bill without receiving stock again', function (): void {
    $service = app(PostDirectGoodsReceipt::class);
    $receipt = $service->handle(exceptionReceiptInput());
    $this->actingAs(User::factory()->create(['role' => UserRole::Finance]));
    $disk = Storage::fake(PrivateStorage::diskName());
    $document = 'invoices/supplier-bills/test-bill.pdf';
    $disk->put($document, '%PDF-test');
    $input = [
        'bill_number' => 'BILL-001', 'invoice_date' => today()->toDateString(), 'due_date' => today()->addDays(30)->toDateString(),
        'subtotal' => 400, 'tax_amount' => 60, 'document' => $document,
    ];
    $invoice = $service->recordSupplierBill($receipt, $input);

    expect($receipt->supplierBill()->sole()->is($invoice))->toBeTrue()
        ->and($invoice->partner_id)->toBe($receipt->supplier_id)
        ->and((float) $invoice->total_amount)->toBe(460.0)
        ->and($invoice->file_path)->toBe($document)
        ->and(StockMovement::count())->toBe(1)
        ->and(fn () => $service->recordSupplierBill($receipt, $input))->toThrow(ValidationException::class)
        ->and(JournalEntry::count())->toBe(0);

    $service->unmatchSupplierBill($receipt, 'Wrong supplier PDF');
    expect($receipt->supplierBill()->exists())->toBeFalse()
        ->and($invoice->refresh()->options['unmatched_receipt_id'])->toBe($receipt->id)
        ->and($invoice->status)->toBe('cancelled');
});

it('protects imported counts from stale posting and closes lost tools without restocking', function (): void {
    $warehouse = Warehouse::factory()->create();
    $item = InventoryItem::factory()->create();
    InventoryBalance::create(['inventory_item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity_on_hand' => 115]);
    $adjustment = StockAdjustment::create(['warehouse_id' => $warehouse->id, 'adjustment_date' => today(), 'status' => 'draft', 'reason' => 'Count']);
    $adjustment->items()->create([
        'inventory_item_id' => $item->id, 'system_quantity' => 110, 'new_quantity' => 100,
        'adjustment_quantity' => -10, 'is_imported_count' => true,
    ]);
    expect(fn () => $adjustment->post())->toThrow(ValidationException::class)
        ->and($adjustment->refresh()->posted_at)->toBeNull();

    $checkout = MaintenanceStoreEntry::factory()->create(['type' => MaintenanceStoreEntry::TYPE_TOOL_CHECKOUT, 'quantity' => 2, 'returned_quantity' => 0]);
    $service = app(MaintenanceStore::class);
    $service->disposeTool($checkout, 1, 'lost', 'Missing after shift');
    $service->disposeTool($checkout, 1, 'damaged', 'Broken handle');
    expect($checkout->refresh()->outstandingQuantity())->toBe(0.0)
        ->and(StockMovement::count())->toBe(0)
        ->and(fn () => $service->returnTool($checkout, 1))->toThrow(ValidationException::class);
});

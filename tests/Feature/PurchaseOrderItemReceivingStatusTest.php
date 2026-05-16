<?php

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryItem;
use App\Models\Partner;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createReceivablePurchaseOrderItem(float $quantity): array
{
    $warehouse = Warehouse::factory()->create();
    $supplier = Partner::factory()->create(['is_supplier' => true]);
    $item = InventoryItem::create([
        'name' => 'Receiving Test Item',
        'sku' => 'RECEIVE-'.fake()->unique()->numberBetween(1000, 9999),
        'unit' => 'piece',
        'purchase_unit' => 'piece',
        'conversion_factor' => 1,
        'type' => 'raw_material',
        'is_sellable' => false,
        'price' => 10,
        'average_cost' => 10,
    ]);

    $purchaseOrder = PurchaseOrder::create([
        'po_number' => 'PO-'.fake()->unique()->numberBetween(1000, 9999),
        'partner_id' => $supplier->id,
        'order_date' => now(),
        'status' => 'approved',
    ]);

    $purchaseOrderItem = PurchaseOrderItem::create([
        'purchase_order_id' => $purchaseOrder->id,
        'inventory_item_id' => $item->id,
        'quantity' => $quantity,
        'received_quantity' => 0,
        'unit_price' => 10,
        'total' => $quantity * 10,
        'status' => 'pending',
    ]);

    return [$warehouse, $purchaseOrder, $purchaseOrderItem];
}

function postGoodsReceipt(Warehouse $warehouse, PurchaseOrder $purchaseOrder, PurchaseOrderItem $purchaseOrderItem, float $quantityReceived): void
{
    $goodsReceipt = GoodsReceipt::create([
        'receipt_number' => 'GR-'.fake()->unique()->numberBetween(1000, 9999),
        'purchase_order_id' => $purchaseOrder->id,
        'warehouse_id' => $warehouse->id,
        'receipt_date' => now(),
        'status' => 'draft',
    ]);

    GoodsReceiptItem::create([
        'goods_receipt_id' => $goodsReceipt->id,
        'purchase_order_item_id' => $purchaseOrderItem->id,
        'quantity_received' => $quantityReceived,
    ]);

    $goodsReceipt->update(['status' => 'posted']);
}

test('purchase order item becomes partially received when a goods receipt posts a partial quantity', function (): void {
    [$warehouse, $purchaseOrder, $purchaseOrderItem] = createReceivablePurchaseOrderItem(10);

    postGoodsReceipt($warehouse, $purchaseOrder, $purchaseOrderItem, 4);

    expect($purchaseOrderItem->fresh())
        ->received_quantity->toBe('4.00')
        ->status->toBe('partially_received');

    expect($purchaseOrder->fresh()->status)->toBe('approved');
});

test('purchase order item becomes received when posted goods receipts satisfy the ordered quantity', function (): void {
    [$warehouse, $purchaseOrder, $purchaseOrderItem] = createReceivablePurchaseOrderItem(10);

    postGoodsReceipt($warehouse, $purchaseOrder, $purchaseOrderItem, 10);

    expect($purchaseOrderItem->fresh())
        ->received_quantity->toBe('10.00')
        ->status->toBe('received');

    expect($purchaseOrder->fresh()->status)->toBe('received');
});

test('purchase order item moves from partial to received after multiple posted goods receipts', function (): void {
    [$warehouse, $purchaseOrder, $purchaseOrderItem] = createReceivablePurchaseOrderItem(10);

    postGoodsReceipt($warehouse, $purchaseOrder, $purchaseOrderItem, 4);
    postGoodsReceipt($warehouse, $purchaseOrder, $purchaseOrderItem, 6);

    expect($purchaseOrderItem->fresh())
        ->received_quantity->toBe('10.00')
        ->status->toBe('received');

    expect($purchaseOrder->fresh()->status)->toBe('received');
});

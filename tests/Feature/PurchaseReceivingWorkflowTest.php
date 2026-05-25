<?php

namespace Tests\Feature;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\Partner;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseReceivingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_receipt_posts_inventory_balance()
    {
        $warehouse = Warehouse::create([
            'name' => 'Raw Materials',
            'code' => 'RAW',
        ]);

        $supplier = Partner::create([
            'name' => 'Supply Co',
            'is_supplier' => true,
        ]);

        $item = InventoryItem::create([
            'name' => '50gsm Bond Paper',
            'sku' => 'BOND-50',
            'unit' => 'Sheet',
            'purchase_unit' => 'Ream',
            'conversion_factor' => 500,
            'type' => 'raw_material',
            'is_sellable' => false,
            'price' => 4.00,
            'average_cost' => 4.00,
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-001',
            'order_date' => now(),
            'partner_id' => $supplier->id,
            'status' => 'approved',
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'inventory_item_id' => $item->id,
            'quantity' => 10,
            'unit_price' => 1500,
            'total' => 15000,
        ]);

        $goodsReceipt = GoodsReceipt::create([
            'receipt_number' => 'GR-TEST-001',
            'receipt_date' => now(),
            'purchase_order_id' => $po->id,
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
            'posted_at' => null,
        ]);

        GoodsReceiptItem::create([
            'goods_receipt_id' => $goodsReceipt->id,
            'purchase_order_item_id' => $poItem->id,
            'quantity_received' => 10,
        ]);

        $goodsReceipt->update(['status' => 'posted']);

        $this->assertDatabaseHas('goods_receipts', [
            'id' => $goodsReceipt->id,
            'status' => 'posted',
        ]);

        $balance = InventoryBalance::where([
            'inventory_item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
        ])->first();

        $this->assertNotNull($balance);
        $this->assertEquals(5000.00, (float) $balance->quantity_on_hand);
    }
}

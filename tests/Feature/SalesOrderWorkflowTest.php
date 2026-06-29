<?php

namespace Tests\Feature;

use App\Enums\PaymentTransactionType;
use App\Models\Account;
use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesOrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('logging.default', 'errorlog');
    }

    public function test_cash_sales_order_completion_creates_stock_movement_sale_and_payment_entries()
    {
        $warehouse = Warehouse::create([
            'name' => 'Sales Warehouse',
            'code' => 'SALE',
        ]);

        $customer = Partner::create([
            'name' => 'Retail Customer',
            'is_customer' => true,
        ]);

        $item = InventoryItem::create([
            'name' => 'Sticker Sheet',
            'sku' => 'STK-001',
            'unit' => 'Sheet',
            'purchase_unit' => 'Sheet',
            'conversion_factor' => 1,
            'type' => 'finished_good',
            'is_sellable' => true,
            'price' => 1.50,
            'average_cost' => 1.50,
        ]);

        InventoryBalance::create([
            'inventory_item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'quantity_on_hand' => 100,
        ]);

        $salesOrder = SalesOrder::create([
            'warehouse_id' => $warehouse->id,
            'partner_id' => $customer->id,
            'order_date' => now(),
            'payment_mode' => 'cash',
            'payment_method' => 'cash',
            'payment_reference' => 'POS-001',
            'status' => 'draft',
        ]);

        SalesOrderItem::create([
            'sales_order_id' => $salesOrder->id,
            'inventory_item_id' => $item->id,
            'quantity' => 20,
            'unit_price' => 10.00,
            'total' => 200.00,
        ]);

        $salesOrder->update(['status' => 'completed']);

        $this->assertDatabaseHas('inventory_balances', [
            'inventory_item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'quantity_on_hand' => 80.00,
        ]);

        $this->assertDatabaseHas('journal_entries', [
            'source_type' => SalesOrder::class,
            'source_id' => $salesOrder->id,
            'status' => 'posted',
        ]);

        $this->assertDatabaseHas('payments', [
            'partner_id' => $customer->id,
            'amount' => 230.00,
            'transaction_type' => PaymentTransactionType::CASH_SALE_RECEIPT->value,
            'method' => 'cash',
        ]);

        $payment = Payment::query()->first();

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'payable_type' => SalesOrder::class,
            'payable_id' => $salesOrder->id,
            'amount' => 230.00,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'reference_type' => SalesOrder::class,
            'reference_id' => $salesOrder->id,
            'inventory_item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => -20,
            'type' => 'sale',
        ]);

        $cashAccount = Account::getSystemAccount('1000', 'Cash in Hand', 'Asset');

        $this->assertDatabaseHas('journal_items', [
            'account_id' => $cashAccount->id,
            'debit' => 230.00,
            'credit' => 0.00,
        ]);
    }

    public function test_sales_order_cannot_be_submitted_when_insufficient_stock()
    {
        $this->expectException(\Exception::class);

        $warehouse = Warehouse::create([
            'name' => 'Sales Warehouse 2',
            'code' => 'SALE2',
        ]);

        $customer = Partner::create([
            'name' => 'Retail Customer 2',
            'is_customer' => true,
        ]);

        $item = InventoryItem::create([
            'name' => 'Vinyl Label',
            'sku' => 'VINYL-001',
            'unit' => 'Roll',
            'purchase_unit' => 'Roll',
            'conversion_factor' => 1,
            'type' => 'finished_good',
            'is_sellable' => true,
            'price' => 20.00,
            'average_cost' => 15.00,
        ]);

        InventoryBalance::create([
            'inventory_item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'quantity_on_hand' => 5,
        ]);

        $salesOrder = SalesOrder::create([
            'warehouse_id' => $warehouse->id,
            'partner_id' => $customer->id,
            'order_date' => now(),
            'status' => 'draft',
        ]);

        SalesOrderItem::create([
            'sales_order_id' => $salesOrder->id,
            'inventory_item_id' => $item->id,
            'quantity' => 10,
            'unit_price' => 25.00,
            'total' => 250.00,
        ]);

        $salesOrder->update(['status' => SalesOrder::STATUS_SUBMITTED]);
    }

    public function test_credit_sales_order_submission_posts_sale_without_completing_before_payment(): void
    {
        $warehouse = Warehouse::create([
            'name' => 'Credit Sales Warehouse',
            'code' => 'SALE3',
        ]);

        $customer = Partner::create([
            'name' => 'Credit Customer',
            'is_customer' => true,
        ]);

        $item = InventoryItem::create([
            'name' => 'Packaging Box',
            'sku' => 'BOX-001',
            'unit' => 'Piece',
            'purchase_unit' => 'Piece',
            'conversion_factor' => 1,
            'type' => 'finished_good',
            'is_sellable' => true,
            'price' => 15.00,
            'average_cost' => 12.00,
        ]);

        InventoryBalance::create([
            'inventory_item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'quantity_on_hand' => 40,
        ]);

        $salesOrder = SalesOrder::create([
            'warehouse_id' => $warehouse->id,
            'partner_id' => $customer->id,
            'order_date' => now(),
            'payment_mode' => 'credit',
            'status' => 'draft',
        ]);

        SalesOrderItem::create([
            'sales_order_id' => $salesOrder->id,
            'inventory_item_id' => $item->id,
            'quantity' => 10,
            'unit_price' => 20.00,
            'total' => 200.00,
        ]);

        $salesOrder->update(['status' => SalesOrder::STATUS_SUBMITTED]);

        $this->assertDatabaseMissing('payments', [
            'partner_id' => $customer->id,
            'amount' => 200.00,
        ]);

        $this->assertDatabaseHas('journal_entries', [
            'source_type' => SalesOrder::class,
            'source_id' => $salesOrder->id,
            'status' => 'posted',
        ]);

        // Balance is the full order total (subtotal + VAT from default settings)
        $this->assertEqualsWithDelta($salesOrder->fresh()->total, $salesOrder->fresh()->balance, 0.001);
        $this->assertSame(SalesOrder::STATUS_SUBMITTED, $salesOrder->fresh()->status);
    }

    public function test_credit_sales_order_cannot_be_completed_before_full_payment(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Credit sales can only be completed after full payment is received.');

        $warehouse = Warehouse::create([
            'name' => 'Credit Guard Warehouse',
            'code' => 'CGW',
        ]);

        $customer = Partner::create([
            'name' => 'Guarded Credit Customer',
            'is_customer' => true,
        ]);

        $item = InventoryItem::create([
            'name' => 'Guarded Packaging Box',
            'sku' => 'GBOX-001',
            'unit' => 'Piece',
            'purchase_unit' => 'Piece',
            'conversion_factor' => 1,
            'type' => 'finished_good',
            'is_sellable' => true,
            'price' => 15.00,
            'average_cost' => 12.00,
        ]);

        InventoryBalance::create([
            'inventory_item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'quantity_on_hand' => 40,
        ]);

        $salesOrder = SalesOrder::create([
            'warehouse_id' => $warehouse->id,
            'partner_id' => $customer->id,
            'order_date' => now(),
            'payment_mode' => 'credit',
            'status' => SalesOrder::STATUS_DRAFT,
        ]);

        SalesOrderItem::create([
            'sales_order_id' => $salesOrder->id,
            'inventory_item_id' => $item->id,
            'quantity' => 10,
            'unit_price' => 20.00,
            'total' => 200.00,
        ]);

        $salesOrder->update(['status' => SalesOrder::STATUS_COMPLETED]);
    }

    public function test_submitted_credit_sales_order_completes_after_full_payment(): void
    {
        $warehouse = Warehouse::create([
            'name' => 'Paid Credit Warehouse',
            'code' => 'PCW',
        ]);

        $customer = Partner::create([
            'name' => 'Paid Credit Customer',
            'is_customer' => true,
        ]);

        $item = InventoryItem::create([
            'name' => 'Paid Packaging Box',
            'sku' => 'PBOX-001',
            'unit' => 'Piece',
            'purchase_unit' => 'Piece',
            'conversion_factor' => 1,
            'type' => 'finished_good',
            'is_sellable' => true,
            'price' => 15.00,
            'average_cost' => 12.00,
        ]);

        InventoryBalance::create([
            'inventory_item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'quantity_on_hand' => 40,
        ]);

        $salesOrder = SalesOrder::create([
            'warehouse_id' => $warehouse->id,
            'partner_id' => $customer->id,
            'order_date' => now(),
            'payment_mode' => 'credit',
            'status' => SalesOrder::STATUS_DRAFT,
        ]);

        SalesOrderItem::create([
            'sales_order_id' => $salesOrder->id,
            'inventory_item_id' => $item->id,
            'quantity' => 10,
            'unit_price' => 20.00,
            'total' => 200.00,
        ]);

        $salesOrder->update(['status' => SalesOrder::STATUS_SUBMITTED]);

        Payment::create([
            'partner_id' => $customer->id,
            'amount' => $salesOrder->fresh()->balance,
            'direction' => 'inbound',
            'transaction_type' => 'customer_receipt',
            'method' => 'cash',
            'reference' => 'Paid in full',
            'payment_date' => now(),
            'payable_type' => SalesOrder::class,
            'payable_id' => $salesOrder->id,
        ]);

        $this->assertSame(SalesOrder::STATUS_COMPLETED, $salesOrder->fresh()->status);
    }

    public function test_purchase_unit_sales_and_void_returns_use_base_stock_quantities(): void
    {
        $warehouse = Warehouse::create([
            'name' => 'Purchase Unit Sales Warehouse',
            'code' => 'PUSW',
        ]);

        $customer = Partner::create([
            'name' => 'Purchase Unit Customer',
            'is_customer' => true,
        ]);

        $item = InventoryItem::create([
            'name' => 'DP70100',
            'sku' => 'DP70100',
            'unit' => 'Sheet',
            'purchase_unit' => 'Ream',
            'conversion_factor' => 500,
            'type' => 'finished_good',
            'is_sellable' => true,
            'price' => 500.00,
            'average_cost' => 1.00,
        ]);

        InventoryBalance::create([
            'inventory_item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'quantity_on_hand' => 1000,
        ]);

        $salesOrder = SalesOrder::create([
            'warehouse_id' => $warehouse->id,
            'partner_id' => $customer->id,
            'order_date' => now(),
            'payment_mode' => 'credit',
            'status' => 'draft',
        ]);

        $salesOrderItem = SalesOrderItem::create([
            'sales_order_id' => $salesOrder->id,
            'inventory_item_id' => $item->id,
            'quantity' => 1,
            'unit_label' => 'Ream',
            'unit_price' => 500.00,
            'total' => 500.00,
        ]);

        $salesOrder->update(['status' => SalesOrder::STATUS_SUBMITTED]);

        $this->assertSame(500.0, $salesOrderItem->fresh()->baseQuantityForStockMovement());

        $this->assertDatabaseHas('stock_movements', [
            'reference_type' => SalesOrder::class,
            'reference_id' => $salesOrder->id,
            'inventory_item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => -500.00,
            'type' => 'sale',
        ]);

        $this->assertStringContainsString(
            'baseQuantityForStockMovement()',
            file_get_contents(base_path('app/Filament/Resources/SalesOrders/Tables/SalesOrdersTable.php')),
        );
    }

    public function test_purchase_unit_sales_conversion_tolerates_legacy_unit_label_casing(): void
    {
        $warehouse = Warehouse::create([
            'name' => 'Legacy Unit Warehouse',
            'code' => 'LUW',
        ]);

        $customer = Partner::create([
            'name' => 'Legacy Unit Customer',
            'is_customer' => true,
        ]);

        $item = InventoryItem::create([
            'name' => 'Case Sensitive Ream',
            'sku' => 'CASE-REAM',
            'unit' => 'Sheet',
            'purchase_unit' => 'Ream',
            'conversion_factor' => 500,
            'type' => 'finished_good',
            'is_sellable' => true,
            'price' => 500.00,
            'average_cost' => 1.00,
        ]);

        InventoryBalance::create([
            'inventory_item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'quantity_on_hand' => 1000,
        ]);

        $salesOrder = SalesOrder::create([
            'warehouse_id' => $warehouse->id,
            'partner_id' => $customer->id,
            'order_date' => now(),
            'payment_mode' => 'credit',
            'status' => 'draft',
        ]);

        $salesOrderItem = SalesOrderItem::create([
            'sales_order_id' => $salesOrder->id,
            'inventory_item_id' => $item->id,
            'quantity' => 1,
            'unit_label' => ' ream ',
            'unit_price' => 500.00,
            'total' => 500.00,
        ]);

        $salesOrder->update(['status' => SalesOrder::STATUS_SUBMITTED]);

        $this->assertTrue($salesOrderItem->fresh()->usesPurchaseUnit());
        $this->assertSame(500.0, $salesOrderItem->fresh()->baseQuantityForStockMovement());

        $this->assertDatabaseHas('stock_movements', [
            'reference_type' => SalesOrder::class,
            'reference_id' => $salesOrder->id,
            'inventory_item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => -500.00,
            'type' => 'sale',
        ]);
    }
}

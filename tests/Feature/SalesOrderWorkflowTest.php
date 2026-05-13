<?php

namespace Tests\Feature;

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
            'transaction_type' => 'customer_receipt',
            'method' => 'cash',
        ]);

        $payment = Payment::query()->first();

        $this->assertDatabaseHas('payment_allocations', [
            'payment_id' => $payment->id,
            'allocatable_type' => SalesOrder::class,
            'allocatable_id' => $salesOrder->id,
            'allocated_amount' => 230.00,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'reference_type' => SalesOrder::class,
            'reference_id' => $salesOrder->id,
            'inventory_item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => -20,
            'type' => 'sale',
        ]);

        $arAccount = Account::getSystemAccount(Account::CODE_AR, 'Accounts Receivable', 'Asset');

        $this->assertDatabaseHas('journal_items', [
            'account_id' => $arAccount->id,
            'debit' => 230.00,
            'credit' => 0.00,
        ]);
    }

    public function test_sales_order_cannot_complete_when_insufficient_stock()
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

        $salesOrder->update(['status' => 'completed']);
    }

    public function test_credit_sales_order_completion_posts_sale_without_creating_payment(): void
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

        $salesOrder->update(['status' => 'completed']);

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
    }
}

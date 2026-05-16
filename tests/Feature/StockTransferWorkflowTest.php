<?php

namespace Tests\Feature;

use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Warehouse;
use App\Support\StockTransferQuantity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockTransferWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_transfer_reduces_source_and_increases_destination()
    {
        $fromWarehouse = Warehouse::create([
            'name' => 'Source Warehouse',
            'code' => 'SRC',
        ]);

        $toWarehouse = Warehouse::create([
            'name' => 'Destination Warehouse',
            'code' => 'DST',
        ]);

        $item = InventoryItem::create([
            'name' => 'Corrugated Board',
            'sku' => 'BOARD-CRC',
            'unit' => 'Sheet',
            'purchase_unit' => 'Bundle',
            'conversion_factor' => 250,
            'type' => 'raw_material',
            'is_sellable' => false,
            'price' => 12.00,
            'average_cost' => 12.00,
        ]);

        InventoryBalance::create([
            'inventory_item_id' => $item->id,
            'warehouse_id' => $fromWarehouse->id,
            'quantity_on_hand' => 500,
        ]);

        $transfer = StockTransfer::create([
            'transfer_number' => 'ST-TEST-001',
            'from_warehouse_id' => $fromWarehouse->id,
            'to_warehouse_id' => $toWarehouse->id,
            'transfer_date' => now(),
            'status' => 'draft',
        ]);

        StockTransferItem::create([
            'stock_transfer_id' => $transfer->id,
            'inventory_item_id' => $item->id,
            'quantity' => 150,
        ]);

        $transfer->update(['status' => 'completed']);

        $this->assertSame('completed', $transfer->fresh()->status);

        $this->assertDatabaseHas('inventory_balances', [
            'inventory_item_id' => $item->id,
            'warehouse_id' => $fromWarehouse->id,
            'quantity_on_hand' => 350.00,
        ]);

        $this->assertDatabaseHas('inventory_balances', [
            'inventory_item_id' => $item->id,
            'warehouse_id' => $toWarehouse->id,
            'quantity_on_hand' => 150.00,
        ]);
    }

    public function test_stock_transfer_cannot_complete_when_insufficient_stock()
    {
        $this->expectException(\Exception::class);

        $fromWarehouse = Warehouse::create([
            'name' => 'Source Warehouse 2',
            'code' => 'SRC2',
        ]);

        $toWarehouse = Warehouse::create([
            'name' => 'Destination Warehouse 2',
            'code' => 'DST2',
        ]);

        $item = InventoryItem::create([
            'name' => 'Adhesive Tape',
            'sku' => 'TAPE-ADH',
            'unit' => 'Roll',
            'purchase_unit' => 'Roll',
            'conversion_factor' => 1,
            'type' => 'raw_material',
            'is_sellable' => false,
            'price' => 4.50,
            'average_cost' => 4.50,
        ]);

        InventoryBalance::create([
            'inventory_item_id' => $item->id,
            'warehouse_id' => $fromWarehouse->id,
            'quantity_on_hand' => 50,
        ]);

        $transfer = StockTransfer::create([
            'transfer_number' => 'ST-TEST-002',
            'from_warehouse_id' => $fromWarehouse->id,
            'to_warehouse_id' => $toWarehouse->id,
            'transfer_date' => now(),
            'status' => 'draft',
        ]);

        StockTransferItem::create([
            'stock_transfer_id' => $transfer->id,
            'inventory_item_id' => $item->id,
            'quantity' => 100,
        ]);

        try {
            $transfer->update(['status' => 'completed']);
        } catch (\Exception) {
            $this->assertSame('draft', $transfer->fresh()->status);

            throw new \Exception;
        }
    }

    public function test_post_marks_transfer_completed_and_does_not_duplicate_movements()
    {
        $fromWarehouse = Warehouse::create([
            'name' => 'Posting Source',
            'code' => 'PSRC',
        ]);

        $toWarehouse = Warehouse::create([
            'name' => 'Posting Destination',
            'code' => 'PDST',
        ]);

        $item = InventoryItem::create([
            'name' => 'Transfer Test Item',
            'sku' => 'TRANSFER-TEST',
            'unit' => 'Piece',
            'purchase_unit' => 'Piece',
            'conversion_factor' => 1,
            'type' => 'raw_material',
            'is_sellable' => false,
            'price' => 10.00,
            'average_cost' => 10.00,
        ]);

        InventoryBalance::create([
            'inventory_item_id' => $item->id,
            'warehouse_id' => $fromWarehouse->id,
            'quantity_on_hand' => 20,
        ]);

        $transfer = StockTransfer::create([
            'transfer_number' => 'ST-TEST-003',
            'from_warehouse_id' => $fromWarehouse->id,
            'to_warehouse_id' => $toWarehouse->id,
            'transfer_date' => now(),
            'status' => 'draft',
        ]);

        StockTransferItem::create([
            'stock_transfer_id' => $transfer->id,
            'inventory_item_id' => $item->id,
            'quantity' => 5,
        ]);

        $transfer->post();
        $transfer->fresh()->post();

        $this->assertSame('completed', $transfer->fresh()->status);

        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertDatabaseHas('inventory_balances', [
            'inventory_item_id' => $item->id,
            'warehouse_id' => $fromWarehouse->id,
            'quantity_on_hand' => 15.00,
        ]);
        $this->assertDatabaseHas('inventory_balances', [
            'inventory_item_id' => $item->id,
            'warehouse_id' => $toWarehouse->id,
            'quantity_on_hand' => 5.00,
        ]);
    }

    public function test_stock_transfer_purchase_unit_quantity_is_converted_to_base_units_when_posted()
    {
        $fromWarehouse = Warehouse::create([
            'name' => 'Purchase Unit Source',
            'code' => 'PUS',
        ]);

        $toWarehouse = Warehouse::create([
            'name' => 'Purchase Unit Destination',
            'code' => 'PUD',
        ]);

        $item = InventoryItem::create([
            'name' => 'Offset Paper',
            'sku' => 'PAPER-OFFSET',
            'unit' => 'Sheet',
            'purchase_unit' => 'Ream',
            'conversion_factor' => 250,
            'type' => 'raw_material',
            'is_sellable' => false,
            'price' => 500.00,
            'average_cost' => 2.00,
        ]);

        InventoryBalance::create([
            'inventory_item_id' => $item->id,
            'warehouse_id' => $fromWarehouse->id,
            'quantity_on_hand' => 1000,
        ]);

        $transfer = StockTransfer::create([
            'transfer_number' => 'ST-PURCHASE-UNIT',
            'from_warehouse_id' => $fromWarehouse->id,
            'to_warehouse_id' => $toWarehouse->id,
            'transfer_date' => now(),
            'status' => 'draft',
        ]);

        StockTransferItem::create([
            'stock_transfer_id' => $transfer->id,
            'inventory_item_id' => $item->id,
            'quantity' => StockTransferQuantity::baseQuantity($item, 2),
        ]);

        $transfer->post();

        $this->assertDatabaseHas('stock_transfer_items', [
            'stock_transfer_id' => $transfer->id,
            'inventory_item_id' => $item->id,
            'quantity' => 500.00,
        ]);

        $this->assertDatabaseHas('inventory_balances', [
            'inventory_item_id' => $item->id,
            'warehouse_id' => $fromWarehouse->id,
            'quantity_on_hand' => 500.00,
        ]);

        $this->assertDatabaseHas('inventory_balances', [
            'inventory_item_id' => $item->id,
            'warehouse_id' => $toWarehouse->id,
            'quantity_on_hand' => 500.00,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'inventory_item_id' => $item->id,
            'warehouse_id' => $fromWarehouse->id,
            'type' => 'transfer_out',
            'quantity' => -500.00,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'inventory_item_id' => $item->id,
            'warehouse_id' => $toWarehouse->id,
            'type' => 'transfer_in',
            'quantity' => 500.00,
        ]);
    }

    public function test_stock_transfer_quantity_display_falls_back_to_base_units_without_purchase_unit()
    {
        $item = InventoryItem::create([
            'name' => 'Loose Ink',
            'sku' => 'INK-LOOSE',
            'unit' => 'Litre',
            'purchase_unit' => null,
            'conversion_factor' => null,
            'type' => 'raw_material',
            'is_sellable' => false,
            'price' => 30.00,
            'average_cost' => 30.00,
        ]);

        $data = StockTransferQuantity::convertRepeaterDataToDisplayUnits([
            'inventory_item_id' => $item->id,
            'quantity' => 12.5,
        ]);

        $saved = StockTransferQuantity::convertRepeaterDataToBaseUnits([
            'inventory_item_id' => $item->id,
            'quantity' => 12.5,
        ]);

        $this->assertSame('Litre', $data['unit_label']);
        $this->assertSame(12.5, $data['quantity']);
        $this->assertSame(12.5, $saved['quantity']);
    }

    public function test_stock_transfer_quantity_formatter_uses_purchase_units_when_available()
    {
        $item = InventoryItem::create([
            'name' => 'Offset Paper Formatter',
            'sku' => 'PAPER-FORMATTER',
            'unit' => 'Sheet',
            'purchase_unit' => 'Ream',
            'conversion_factor' => 250,
            'type' => 'raw_material',
            'is_sellable' => false,
            'price' => 500.00,
            'average_cost' => 2.00,
        ]);

        $this->assertSame('2 Ream', StockTransferQuantity::formattedQuantity($item, 500));
        $this->assertSame('-2 Ream', StockTransferQuantity::formattedQuantity($item, -500));
    }

    public function test_stock_tables_render_quantities_with_units()
    {
        $paths = [
            'app/Filament/Resources/StockMovements/Tables/StockMovementsTable.php',
            'app/Filament/Resources/Warehouses/RelationManagers/StockMovementsRelationManager.php',
            'app/Filament/Warehouse/Widgets/RecentStockMovementsTable.php',
            'app/Filament/Resources/StockTransfers/Tables/StockTransfersTable.php',
        ];

        foreach ($paths as $path) {
            $this->assertStringContainsString(
                StockTransferQuantity::class,
                file_get_contents(base_path($path)),
            );
        }
    }
}

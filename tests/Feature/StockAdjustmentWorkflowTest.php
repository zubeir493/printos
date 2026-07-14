<?php

namespace Tests\Feature;

use App\Filament\Resources\StockAdjustments\StockAdjustmentResource;
use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\User;
use App\Models\Warehouse;
use App\UserRole;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockAdjustmentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_adjustment_posts_and_updates_balance()
    {
        $warehouse = Warehouse::create([
            'name' => 'Raw Storage',
            'code' => 'RAW2',
        ]);

        $item = InventoryItem::create([
            'name' => 'Binding Glue',
            'sku' => 'GLUE-1L',
            'unit' => 'Litre',
            'purchase_unit' => 'Litre',
            'conversion_factor' => 1,
            'type' => 'raw_material',
            'is_sellable' => false,
            'price' => 12.50,
            'average_cost' => 12.50,
        ]);

        InventoryBalance::create([
            'inventory_item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'quantity_on_hand' => 100,
        ]);

        $adjustment = StockAdjustment::create([
            'warehouse_id' => $warehouse->id,
            'adjustment_date' => now(),
            'status' => 'draft',
            'reason' => 'Cycle count variance',
        ]);

        StockAdjustmentItem::create([
            'stock_adjustment_id' => $adjustment->id,
            'inventory_item_id' => $item->id,
            'system_quantity' => 100,
            'adjustment_quantity' => -20,
            'new_quantity' => 80,
            'difference' => -20,
        ]);

        $adjustment->update(['status' => 'posted']);

        $this->assertDatabaseHas('stock_adjustments', [
            'id' => $adjustment->id,
            'status' => 'posted',
        ]);

        $this->assertDatabaseHas('inventory_balances', [
            'inventory_item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'quantity_on_hand' => 80.00,
        ]);

        $this->assertFalse(StockAdjustmentResource::canEdit($adjustment->fresh()));
    }

    public function test_stock_adjustment_view_page_renders_after_creation()
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->actingAs(User::factory()->create([
            'role' => UserRole::Admin,
        ]));

        $warehouse = Warehouse::create([
            'name' => 'View Storage',
            'code' => 'VIEW',
        ]);

        $adjustment = StockAdjustment::create([
            'warehouse_id' => $warehouse->id,
            'adjustment_date' => now(),
            'status' => 'draft',
            'reason' => 'Cycle count variance',
        ]);

        $this->get(StockAdjustmentResource::getUrl('view', ['record' => $adjustment]))
            ->assertSuccessful();
    }

    public function test_stock_adjustment_cannot_post_when_resulting_stock_is_negative()
    {
        $this->expectException(\Exception::class);

        $warehouse = Warehouse::create([
            'name' => 'Raw Storage 2',
            'code' => 'RAW3',
        ]);

        $item = InventoryItem::create([
            'name' => 'Steel Clips',
            'sku' => 'CLIP-STEEL',
            'unit' => 'Piece',
            'purchase_unit' => 'Piece',
            'conversion_factor' => 1,
            'type' => 'raw_material',
            'is_sellable' => false,
            'price' => 0.25,
            'average_cost' => 0.25,
        ]);

        InventoryBalance::create([
            'inventory_item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'quantity_on_hand' => 10,
        ]);

        $adjustment = StockAdjustment::create([
            'warehouse_id' => $warehouse->id,
            'adjustment_date' => now(),
            'status' => 'draft',
            'reason' => 'Clearance error',
        ]);

        StockAdjustmentItem::create([
            'stock_adjustment_id' => $adjustment->id,
            'inventory_item_id' => $item->id,
            'system_quantity' => 10,
            'adjustment_quantity' => -20,
            'new_quantity' => -10,
            'difference' => -20,
        ]);

        $adjustment->update(['status' => 'posted']);
    }
}

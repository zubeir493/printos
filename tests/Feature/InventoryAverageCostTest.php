<?php

use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('initializes inventory average cost from the actual first receipt without losing fractional base-unit prices', function (): void {
    $item = InventoryItem::factory()->create(['unit' => 'sheet', 'purchase_unit' => 'ream', 'conversion_factor' => 500, 'average_cost' => 0]);
    $warehouse = Warehouse::factory()->create();
    $movement = app(InventoryService::class)->receiveStockInPurchaseUnit($item->id, $warehouse->id, 1, 1500.01, 'receipt-test', 1);

    expect($movement->unit_cost)->toBe('3.000020')->and($item->refresh()->average_cost)->toBe('3.000020')
        ->and((float) $item->inventoryBalances()->sum('quantity_on_hand'))->toBe(500.0);
});

it('weights new receipts against stock in all warehouses', function (): void {
    $item = InventoryItem::factory()->create(['unit' => 'sheet', 'purchase_unit' => 'ream', 'conversion_factor' => 500, 'average_cost' => 2]);
    $warehouses = Warehouse::factory()->count(2)->create();
    foreach ($warehouses as $warehouse) {
        InventoryBalance::factory()->create(['inventory_item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity_on_hand' => 500]);
    }
    app(InventoryService::class)->receiveStockInPurchaseUnit($item->id, $warehouses[0]->id, 1, 2000, 'receipt-test', 1);
    expect($item->refresh()->average_cost)->toBe('2.666667');
});

it('keeps historic stock with unknown cost unresolved rather than valuing it as free', function (): void {
    $item = InventoryItem::factory()->create(['unit' => 'sheet', 'purchase_unit' => 'ream', 'conversion_factor' => 500, 'average_cost' => 0]);
    $warehouse = Warehouse::factory()->create();
    InventoryBalance::factory()->create(['inventory_item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity_on_hand' => 500]);
    app(InventoryService::class)->receiveStockInPurchaseUnit($item->id, $warehouse->id, 1, 1000, 'receipt-test', 1);
    expect($item->refresh()->average_cost)->toBe('0.000000');
});

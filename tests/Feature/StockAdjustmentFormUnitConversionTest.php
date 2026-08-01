<?php

use App\Filament\Resources\StockAdjustments\Schemas\StockAdjustmentForm;
use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\Warehouse;
use App\Services\StockAdjustmentItemImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('converts raw material stock adjustment quantities between purchase and base units', function (): void {
    $item = InventoryItem::create([
        'name' => 'Offset Paper',
        'sku' => 'PAPER-ADJ',
        'unit' => 'Sheet',
        'purchase_unit' => 'Ream',
        'conversion_factor' => 500,
        'type' => 'raw_material',
        'is_sellable' => false,
        'price' => 1000,
        'average_cost' => 2,
    ]);

    $displayData = StockAdjustmentForm::convertRepeaterDataToDisplayUnits([
        'inventory_item_id' => $item->id,
        'system_quantity' => 1000,
        'adjustment_quantity' => -500,
        'new_quantity' => 500,
        'difference' => -500,
    ]);

    expect($displayData)
        ->toMatchArray([
            'system_quantity' => 2.0,
            'adjustment_quantity' => -1.0,
            'new_quantity' => 1.0,
            'difference' => -1.0,
        ])
        ->and(StockAdjustmentForm::convertRepeaterDataToBaseUnits($displayData))
        ->toMatchArray([
            'system_quantity' => 1000.0,
            'adjustment_quantity' => -500.0,
            'new_quantity' => 500.0,
            'difference' => -500.0,
        ])
        ->and(StockAdjustmentForm::unitPrefixForItemId($item->id))
        ->toBe('Ream');
});

it('keeps normal stock adjustment quantities in base units', function (): void {
    $item = InventoryItem::create([
        'name' => 'Finished Box',
        'sku' => 'BOX-ADJ',
        'unit' => 'Piece',
        'purchase_unit' => 'Carton',
        'conversion_factor' => 24,
        'type' => 'finished_good',
        'is_sellable' => true,
        'price' => 25,
        'average_cost' => 10,
    ]);

    $displayData = StockAdjustmentForm::convertRepeaterDataToDisplayUnits([
        'inventory_item_id' => $item->id,
        'system_quantity' => 48,
        'adjustment_quantity' => -12,
        'new_quantity' => 36,
        'difference' => -12,
    ]);

    expect($displayData)
        ->toMatchArray([
            'system_quantity' => 48.0,
            'adjustment_quantity' => -12.0,
            'new_quantity' => 36.0,
            'difference' => -12.0,
        ])
        ->and(StockAdjustmentForm::convertRepeaterDataToBaseUnits($displayData))
        ->toMatchArray([
            'system_quantity' => 48.0,
            'adjustment_quantity' => -12.0,
            'new_quantity' => 36.0,
            'difference' => -12.0,
        ])
        ->and(StockAdjustmentForm::unitPrefixForItemId($item->id))
        ->toBe('Piece');
});

it('imports stock adjustment rows against the selected warehouse balance', function (): void {
    $warehouse = Warehouse::factory()->create();
    $item = InventoryItem::factory()->create([
        'name' => 'Offset Paper',
        'sku' => 'PAPER-ADJ-IMPORT',
        'unit' => 'Sheet',
        'purchase_unit' => 'Ream',
        'conversion_factor' => 500,
        'type' => 'raw_material',
    ]);

    InventoryBalance::create([
        'inventory_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity_on_hand' => 1000,
    ]);

    $tmp = tempnam(sys_get_temp_dir(), 'stock_adjustment_import_test_');
    $csvPath = $tmp.'.csv';
    file_put_contents($csvPath, "sku,new_quantity\nPAPER-ADJ-IMPORT,1\n");

    try {
        $rows = app(StockAdjustmentItemImportService::class)->importRows($csvPath, $warehouse->id);
    } finally {
        @unlink($csvPath);
        @unlink($tmp);
    }

    expect($rows)->toHaveCount(1)
        ->and($rows[0])->toMatchArray([
            'inventory_item_id' => $item->id,
            'system_quantity' => 2.0,
            'adjustment_quantity' => -1.0,
            'new_quantity' => 1.0,
            'difference' => -1.0,
        ]);
});

it('imports stock adjustment rows with bom csv headers', function (): void {
    $warehouse = Warehouse::factory()->create();
    $item = InventoryItem::factory()->create([
        'sku' => 'BOM-SKU',
        'unit' => 'Piece',
        'type' => 'finished_good',
    ]);

    InventoryBalance::create([
        'inventory_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity_on_hand' => 3,
    ]);

    $tmp = tempnam(sys_get_temp_dir(), 'stock_adjustment_import_bom_test_');
    $csvPath = $tmp.'.csv';
    file_put_contents($csvPath, "\xEF\xBB\xBFsku,new_quantity\n BOM-SKU ,5\n");

    try {
        $rows = app(StockAdjustmentItemImportService::class)->importRows($csvPath, $warehouse->id);
    } finally {
        @unlink($csvPath);
        @unlink($tmp);
    }

    expect($rows[0])->toMatchArray([
        'inventory_item_id' => $item->id,
        'system_quantity' => 3.0,
        'adjustment_quantity' => 2.0,
        'new_quantity' => 5.0,
    ]);
});

it('reports the missing stock adjustment sku', function (): void {
    $warehouse = Warehouse::factory()->create();
    $tmp = tempnam(sys_get_temp_dir(), 'stock_adjustment_import_missing_sku_test_');
    $csvPath = $tmp.'.csv';
    file_put_contents($csvPath, "sku,new_quantity\nNOT-FOUND,5\n");

    try {
        app(StockAdjustmentItemImportService::class)->importRows($csvPath, $warehouse->id);
    } finally {
        @unlink($csvPath);
        @unlink($tmp);
    }
})->throws(RuntimeException::class, 'Unable to match inventory item SKU `NOT-FOUND`.');

it('stock adjustment imports replace blank rows and merge matching items', function (): void {
    $rows = StockAdjustmentForm::mergeImportedRows(
        [
            [
                'inventory_item_id' => null,
                'system_quantity' => 0,
                'adjustment_quantity' => 0,
                'new_quantity' => 0,
                'difference' => 0,
            ],
            [
                'inventory_item_id' => 10,
                'system_quantity' => 5,
                'adjustment_quantity' => 2,
                'new_quantity' => 7,
                'difference' => 2,
            ],
        ],
        [
            [
                'inventory_item_id' => 20,
                'system_quantity' => 8,
                'adjustment_quantity' => -3,
                'new_quantity' => 5,
                'difference' => -3,
            ],
            [
                'inventory_item_id' => 10,
                'system_quantity' => 5,
                'adjustment_quantity' => -4,
                'new_quantity' => 1,
                'difference' => -4,
            ],
        ],
    );

    expect($rows)->toHaveCount(2);
    expect($rows[0]['inventory_item_id'])->toBe(10);
    expect($rows[0]['adjustment_quantity'])->toBe(-2.0);
    expect($rows[0]['new_quantity'])->toBe(3.0);
    expect($rows[0]['difference'])->toBe(-2.0);
    expect($rows[1]['inventory_item_id'])->toBe(20);
    expect($rows[1]['adjustment_quantity'])->toBe(-3);
});

test('stock adjustment import modal links to the example csv template', function (): void {
    expect(file_get_contents(base_path('app/Filament/Resources/StockAdjustments/Schemas/StockAdjustmentForm.php')))
        ->toContain('import-templates/stock-adjustment-items.csv');

    expect(file_get_contents(public_path('import-templates/stock-adjustment-items.csv')))
        ->toContain('sku,new_quantity');
});

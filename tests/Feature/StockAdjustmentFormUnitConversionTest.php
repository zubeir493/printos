<?php

use App\Filament\Resources\StockAdjustments\Schemas\StockAdjustmentForm;
use App\Models\InventoryItem;
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

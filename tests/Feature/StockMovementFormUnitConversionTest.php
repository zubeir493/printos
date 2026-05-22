<?php

use App\Filament\Resources\StockMovements\Schemas\StockMovementForm;
use App\Models\InventoryItem;
use App\Support\StockTransferQuantity;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('converts raw material stock movement quantities from purchase units to base units', function (): void {
    $item = InventoryItem::create([
        'name' => 'Offset Paper',
        'sku' => 'PAPER-OFFSET',
        'unit' => 'Sheet',
        'purchase_unit' => 'Ream',
        'conversion_factor' => 500,
        'type' => 'raw_material',
        'is_sellable' => false,
        'price' => 1000,
        'average_cost' => 2,
    ]);

    expect(StockMovementForm::unitLabelForItemId($item->id))
        ->toBe('Ream')
        ->and(StockMovementForm::baseQuantityForItemId($item->id, 2))
        ->toBe(1000.0)
        ->and(StockTransferQuantity::displayQuantity($item, 1000))
        ->toBe(2.0);
});

it('keeps normal stock movement quantities in base units', function (): void {
    $item = InventoryItem::create([
        'name' => 'Finished Box',
        'sku' => 'BOX-FINISHED',
        'unit' => 'Piece',
        'purchase_unit' => 'Carton',
        'conversion_factor' => 24,
        'type' => 'finished_good',
        'is_sellable' => true,
        'price' => 25,
        'average_cost' => 10,
    ]);

    expect(StockMovementForm::unitLabelForItemId($item->id))
        ->toBe('Piece')
        ->and(StockMovementForm::baseQuantityForItemId($item->id, 2))
        ->toBe(2.0)
        ->and(StockTransferQuantity::displayQuantity($item, 48))
        ->toBe(48.0);
});

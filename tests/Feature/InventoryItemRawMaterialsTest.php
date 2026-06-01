<?php

use App\Models\InventoryItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('raw materials scope only includes raw material inventory items', function (): void {
    $paper = InventoryItem::factory()->create([
        'name' => 'Paper',
        'type' => 'raw_material',
    ]);

    $finishedGood = InventoryItem::factory()->create([
        'name' => 'Finished Book',
        'type' => 'finished_good',
    ]);

    $options = InventoryItem::query()
        ->rawMaterials()
        ->pluck('name', 'id');

    expect($options)
        ->toHaveKey($paper->id)
        ->not->toHaveKey($finishedGood->id);
});

test('raw material category scopes only include matching costing materials', function (): void {
    $paper = InventoryItem::factory()->create([
        'type' => 'raw_material',
        'category' => 'paper',
    ]);
    $ink = InventoryItem::factory()->create([
        'type' => 'raw_material',
        'category' => 'ink',
    ]);
    $glue = InventoryItem::factory()->create([
        'type' => 'raw_material',
        'category' => 'glue',
    ]);
    $finishedPaper = InventoryItem::factory()->create([
        'type' => 'finished_good',
        'category' => 'paper',
    ]);

    expect(InventoryItem::query()->paperMaterials()->pluck('id'))
        ->toContain($paper->id)
        ->not->toContain($ink->id)
        ->not->toContain($finishedPaper->id)
        ->and(InventoryItem::query()->inkMaterials()->pluck('id'))
        ->toContain($ink->id)
        ->not->toContain($paper->id)
        ->and(InventoryItem::query()->glueMaterials()->pluck('id'))
        ->toContain($glue->id);
});

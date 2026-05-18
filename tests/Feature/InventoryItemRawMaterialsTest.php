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

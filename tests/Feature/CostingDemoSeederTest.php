<?php

use App\Models\CostEstimate;
use App\Models\InventoryItem;
use App\Models\Machine;
use App\Models\StockMovement;
use App\Services\Costing\SheetfedCostCalculator;
use Database\Seeders\CostingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('provides every machine operation and raw material category with complete demo examples', function (): void {
    $this->seed(CostingDemoSeeder::class);
    $machines = Machine::query()->where('code', 'like', 'DEMO-COST-%')->get();
    $materials = InventoryItem::query()->where('sku', 'like', 'DEMO-COST-%')->get();
    expect($machines->pluck('operation_type')->unique()->sort()->values()->all())->toBe(collect(array_keys(Machine::OPERATION_TYPES))->sort()->values()->all())
        ->and($materials->pluck('category')->unique()->sort()->values()->all())->toBe(collect(array_keys(InventoryItem::RAW_MATERIAL_CATEGORIES))->sort()->values()->all());
    foreach (CostEstimate::query()->where('estimate_number', 'like', 'DEMO-CE-%')->get() as $estimate) {
        $result = app(SheetfedCostCalculator::class)->calculate($estimate->toArray());
        expect($result->diagnostics)->toBe([], $estimate->estimate_number)->and($result->total)->toBeGreaterThan(0);
    }
    expect(CostEstimate::query()->where('estimate_number', 'like', 'DEMO-CE-%')->count())->toBe(6);
});

it('can be rerun without duplicates, resetting stock or overwriting edited demo rates', function (): void {
    $this->seed(CostingDemoSeeder::class);
    $machine = Machine::where('code', 'DEMO-COST-FOLDING')->firstOrFail();
    $machine->update(['hourly_cost' => 999]);
    $material = InventoryItem::where('sku', 'DEMO-COST-TEXT')->firstOrFail();
    $material->inventoryBalances()->firstOrFail()->update(['quantity_on_hand' => 123]);
    $counts = [Machine::count(), InventoryItem::count(), StockMovement::count(), CostEstimate::count()];
    $this->seed(CostingDemoSeeder::class);
    expect([Machine::count(), InventoryItem::count(), StockMovement::count(), CostEstimate::count()])->toBe($counts)
        ->and((float) $machine->refresh()->hourly_cost)->toBe(999.0)
        ->and((float) $material->inventoryBalances()->firstOrFail()->quantity_on_hand)->toBe(123.0);
});

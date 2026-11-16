<?php

use App\Filament\Resources\CostEstimates\Pages\CreateCostEstimate;
use App\Filament\Resources\CostEstimates\Pages\ViewCostEstimate;
use App\Models\CostEstimate;
use App\Models\DielineImposition;
use App\Models\InventoryItem;
use App\Models\Machine;
use App\Models\Partner;
use App\Models\Setting;
use App\Models\User;
use App\Services\Costing\CostEstimateService;
use App\Services\Costing\SheetfedCostCalculator;
use App\UserRole;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function sheetfedFixture(): array
{
    Setting::createDefault();
    $paper = InventoryItem::factory()->create(['type' => 'raw_material', 'category' => 'paper', 'unit' => 'sheet', 'average_cost' => 2, 'width' => 10, 'height' => 10, 'gsm' => 100, 'printable_sides' => 2, 'grain_direction' => 'long', 'default_waste_percent' => 0]);
    $plate = InventoryItem::factory()->create(['type' => 'raw_material', 'category' => 'plate', 'unit' => 'piece', 'average_cost' => 5]);
    $ink = InventoryItem::factory()->create(['type' => 'raw_material', 'category' => 'ink', 'unit' => 'kg', 'average_cost' => 100, 'consumption_grams_per_m2' => 2]);
    $press = Machine::create(['name' => 'Four-color offset', 'operation_type' => 'printing', 'hourly_cost' => 100, 'production_speed' => 1000, 'speed_unit' => 'sheets_per_hour', 'color_units' => 4, 'setup_time_minutes' => 30, 'setup_waste' => 10, 'run_waste_percent' => 0, 'min_sheet_width_mm' => 100, 'min_sheet_height_mm' => 100, 'max_sheet_width_mm' => 100, 'max_sheet_height_mm' => 100, 'costing_configured' => true, 'is_active' => true]);
    $cutter = Machine::create(['name' => 'Cutter', 'operation_type' => 'cutting', 'hourly_cost' => 10, 'production_speed' => 1000, 'speed_unit' => 'products_per_hour', 'costing_configured' => true, 'is_active' => true]);
    $data = [
        'job_type' => 'labels', 'quantity' => 1000,
        'services' => [
            'costing_version' => 'sheetfed-v2',
            'product' => ['shape' => 'rectangle', 'consumables_confirmed' => true],
            'components' => [[
                'role' => 'stock', 'name' => 'Label stock', 'material_id' => $paper->id, 'machine_id' => $press->id,
                'width_mm' => 40, 'height_mm' => 40, 'bleed_mm' => 0, 'gap_mm' => 0, 'grain_axis' => 'any',
                'front_colors' => 4, 'back_colors' => 4, 'ink_item_id' => $ink->id, 'plate_item_id' => $plate->id,
                'ink_coverage_percent' => 25, 'layout_confirmed' => true,
            ]],
            'finishing' => [['name' => 'Trim labels', 'machine_id' => $cutter->id, 'basis' => 'products']],
            'commercial' => ['pricing_mode' => 'margin', 'profit_percent' => 20],
        ],
    ];

    return compact('data', 'paper', 'plate', 'ink', 'press', 'cutter');
}

it('prices an independently worked offset example including duplex passes, ink, waste and true margin', function (): void {
    $fixture = sheetfedFixture();
    $result = app(SheetfedCostCalculator::class)->calculate($fixture['data']);
    expect($result->diagnostics)->toBe([])
        ->and($result->layouts[0]['ups'])->toBe(4)
        ->and($result->layouts[0]['parent_sheets'])->toBe(270)
        ->and($result->layouts[0]['plates'])->toBe(8)
        ->and($result->layouts[0]['passes'])->toBe(2)
        ->and(collect($result->operations)->firstWhere('machine_id', $fixture['press']->id)['run_hours'])->toBe(0.51)
        ->and($result->subtotal)->toBe(741.68)
        ->and($result->overheadAmount)->toBe(0.0)
        ->and($result->commercial['net_sell'])->toBe(927.1)
        ->and($result->taxAmount)->toBe(139.07)
        ->and($result->total)->toBe(1066.17)
        ->and($result->unitPrice)->toBe(0.9271)
        ->and($result->marginPercent)->toBe(20.0);
});

it('distinguishes markup and achieved margin after discount', function (): void {
    $fixture = sheetfedFixture();
    $data = $fixture['data'];
    $data['services']['commercial'] = ['pricing_mode' => 'markup', 'profit_percent' => 20, 'discount_percent' => 10];
    $result = app(SheetfedCostCalculator::class)->calculate($data);
    expect($result->commercial['sell_before_discount'])->toBe(890.02)
        ->and($result->commercial['net_sell'])->toBe(801.02)
        ->and($result->marginPercent)->toBeLessThan(8)->toBeGreaterThan(7);
});

it('uses perfecting capacity rather than charging a pass for each color', function (): void {
    $fixture = sheetfedFixture();
    $fixture['press']->update(['color_units' => 8, 'perfecting_colors_per_side' => 4]);
    $result = app(SheetfedCostCalculator::class)->calculate($fixture['data']);
    expect($result->layouts[0]['passes'])->toBe(1)
        ->and($result->layouts[0]['parent_sheets'])->toBe(260)
        ->and($result->layouts[0]['plates'])->toBe(8);
});

it('works backwards through finishing rejects and running waste', function (): void {
    $fixture = sheetfedFixture();
    $fixture['cutter']->update(['run_waste_percent' => 10, 'setup_waste' => 5]);
    $fixture['press']->update(['setup_waste' => 0, 'run_waste_percent' => 10]);
    $result = app(SheetfedCostCalculator::class)->calculate($fixture['data']);
    expect($result->layouts[0]['net_press_sheets'])->toBe(280)
        ->and($result->layouts[0]['gross_press_sheets'])->toBe(347)
        ->and($result->layouts[0]['waste_sheets'])->toBe(67);
});

it('keeps unknown costs incomplete rather than using fallback prices', function (): void {
    $fixture = sheetfedFixture();
    $fixture['paper']->update(['average_cost' => 0, 'price' => 999]);
    $result = app(SheetfedCostCalculator::class)->calculate($fixture['data']);
    expect($result->isComplete())->toBeFalse()
        ->and(implode(' ', $result->diagnostics))->toContain('average cost is missing')
        ->and($result->total)->toBe(0.0)->and($result->subtotal)->toBeGreaterThan(0);
});

it('allows a documented customer-supplied material override', function (): void {
    $fixture = sheetfedFixture();
    $fixture['paper']->update(['average_cost' => 0]);
    $data = $fixture['data'];
    $data['services']['components'][0]['unit_cost_override'] = 0;
    $data['services']['components'][0]['override_reason'] = 'Customer supplies the stock';
    $result = app(SheetfedCostCalculator::class)->calculate($data);
    expect($result->isComplete())->toBeTrue()
        ->and(collect($result->lines)->firstWhere('inventory_item_id', $fixture['paper']->id)['total'])->toBe(0.0);
});

it('uses actual density for liquid ink', function (): void {
    $fixture = sheetfedFixture();
    $fixture['ink']->update(['unit' => 'l', 'density_kg_per_litre' => 2]);
    $result = app(SheetfedCostCalculator::class)->calculate($fixture['data']);
    expect(collect($result->lines)->firstWhere('inventory_item_id', $fixture['ink']->id)['quantity'])->toBe(0.003392);
    $fixture['ink']->update(['density_kg_per_litre' => null]);
    expect(app(SheetfedCostCalculator::class)->calculate($fixture['data'])->isComplete())->toBeFalse();
});

it('normalizes stock priced by kilograms using gsm and dimensions', function (): void {
    $fixture = sheetfedFixture();
    $fixture['paper']->update(['unit' => 'kg', 'average_cost' => 2000]);
    $result = app(SheetfedCostCalculator::class)->calculate($fixture['data']);
    $paperLine = collect($result->lines)->firstWhere('inventory_item_id', $fixture['paper']->id);
    expect($paperLine['quantity'])->toBe(0.27)->and($paperLine['total'])->toBe(540.0);
});

it('blocks missing machines, impossible layouts and invalid waste', function (string $field, mixed $value, string $message): void {
    $fixture = sheetfedFixture();
    $data = $fixture['data'];
    $data['services']['components'][0][$field] = $value;
    $result = app(SheetfedCostCalculator::class)->calculate($data);
    expect($result->isComplete())->toBeFalse()->and(implode(' ', $result->diagnostics))->toContain($message);
})->with([
    ['machine_id', null, 'select an active machine'],
    ['width_mm', 1000, 'no valid layout'],
    ['layout_confirmed', false, 'review and confirm'],
    ['waste_percent_override', 100, 'less than 100'],
]);

it('requires composite self-adhesive stock for stickers', function (): void {
    $fixture = sheetfedFixture();
    $data = $fixture['data'];
    $data['job_type'] = 'stickers';
    $fixture['cutter']->update(['operation_type' => 'kiss_cutting', 'speed_unit' => 'sheets_per_hour', 'max_sheet_width_mm' => 100, 'max_sheet_height_mm' => 100]);
    $data['services']['finishing'][0]['basis'] = 'sheets';
    $data['services']['finishing'][0]['component_role'] = 'stock';
    $tool = InventoryItem::factory()->create(['type' => 'raw_material', 'category' => 'die', 'unit' => 'piece', 'average_cost' => 10]);
    $data['services']['consumables'] = [['material_id' => $tool->id, 'quantity_per_basis' => 1, 'basis' => 'fixed']];
    expect(app(SheetfedCostCalculator::class)->calculate($data)->isComplete())->toBeFalse();
    $fixture['paper']->update(['is_self_adhesive' => true]);
    expect(app(SheetfedCostCalculator::class)->calculate($data)->isComplete())->toBeTrue();
});

it('freezes complete estimates against later source changes and explicit recalculation', function (): void {
    $fixture = sheetfedFixture();
    $estimate = CostEstimate::factory()->create($fixture['data']);
    $service = app(CostEstimateService::class);
    $estimate = $service->finalize($estimate);
    $total = $estimate->total;
    $fixture['paper']->update(['average_cost' => 100]);
    expect($estimate->calculation_snapshot['layouts'][0]['parent_sheets'])->toBe(270)
        ->and($estimate->currency_code)->toBe('ETB')
        ->and(fn () => $service->recalculate($estimate))->toThrow(ValidationException::class)
        ->and($estimate->refresh()->total)->toBe($total);
});

it('blocks incomplete finalization and converts a complete estimate to a balanced proforma', function (): void {
    $fixture = sheetfedFixture();
    $data = $fixture['data'];
    $data['services']['components'][0]['layout_confirmed'] = false;
    $estimate = CostEstimate::factory()->create($data);
    $service = app(CostEstimateService::class);
    expect(fn () => $service->finalize($estimate))->toThrow(ValidationException::class);
    expect($estimate->refresh()->status)->toBe('draft');
    $estimate->update(['services' => $fixture['data']['services']]);
    $proforma = $service->createProforma($estimate, ['partner_id' => Partner::factory()->create(['is_customer' => true])->id]);
    expect($proforma->subtotal)->toBe('927.10')
        ->and($proforma->tasks->sum('task_cost'))->toBe(927.1)
        ->and($proforma->tax_amount)->toBe('139.07')
        ->and($proforma->total)->toBe('1066.17');
});

it('splits a 68 page book into real signature groups and costs inventory and binding machinery', function (): void {
    $fixture = sheetfedFixture();
    $fixture['press']->update(['setup_waste' => 0, 'setup_time_minutes' => 0]);
    $machines = [];
    foreach (['folding' => 'sheets_per_hour', 'collating' => 'products_per_hour', 'perfect_binding' => 'products_per_hour'] as $operation => $unit) {
        $machines[$operation] = Machine::create(['name' => $operation, 'operation_type' => $operation, 'speed_unit' => $unit, 'hourly_cost' => 10, 'production_speed' => 1000, 'costing_configured' => true, 'is_active' => true, 'max_sheet_width_mm' => 100, 'max_sheet_height_mm' => 100]);
    }
    $glue = InventoryItem::factory()->create(['type' => 'raw_material', 'category' => 'glue', 'unit' => 'kg', 'average_cost' => 10]);
    $component = $fixture['data']['services']['components'][0];
    $data = $fixture['data'];
    $data['job_type'] = 'books';
    $data['services']['product'] = ['width_mm' => 10, 'height_mm' => 20, 'page_count' => 68, 'binding' => 'perfect', 'consumables_confirmed' => true];
    $data['services']['components'] = [
        [...$component, 'name' => 'Text', 'role' => 'text', 'signature_pages' => 16, 'front_colors' => 1, 'back_colors' => 1],
        [...$component, 'name' => 'Cover', 'role' => 'cover', 'width_mm' => 40, 'height_mm' => 20, 'back_colors' => 0],
    ];
    $data['services']['finishing'] = [
        ['name' => 'Fold', 'machine_id' => $machines['folding']->id, 'basis' => 'sheets', 'component_role' => 'text'],
        ['name' => 'Collate', 'machine_id' => $machines['collating']->id, 'basis' => 'products'],
        ['name' => 'Bind', 'machine_id' => $machines['perfect_binding']->id, 'basis' => 'products'],
        ...$data['services']['finishing'],
    ];
    $data['services']['consumables'] = [['name' => 'Binding glue', 'material_id' => $glue->id, 'basis' => 'fixed', 'quantity_per_basis' => 1]];
    $result = app(SheetfedCostCalculator::class)->calculate($data);
    expect($result->diagnostics)->toBe([])
        ->and(array_column($result->layouts, 'signature_pages'))->toBe([16, 4, 0])
        ->and(array_column($result->layouts, 'forms'))->toBe([4, 1, 1])
        ->and(array_sum(array_column($result->layouts, 'parent_sheets')))->toBe(1140)
        ->and(array_sum(array_column($result->layouts, 'plates')))->toBe(14);
});

it('prices folding cartons from a confirmed measured blank and machine route', function (): void {
    $fixture = sheetfedFixture();
    $die = Machine::create(['name' => 'Die cutter', 'operation_type' => 'die_cutting', 'speed_unit' => 'sheets_per_hour', 'hourly_cost' => 20, 'production_speed' => 1000, 'costing_configured' => true, 'is_active' => true, 'max_sheet_width_mm' => 100, 'max_sheet_height_mm' => 100]);
    $fixture['cutter']->update(['operation_type' => 'folder_gluer']);
    $data = $fixture['data'];
    $data['job_type'] = 'packages';
    $data['services']['components'][0]['blank_dimensions_confirmed'] = true;
    $tool = InventoryItem::factory()->create(['type' => 'raw_material', 'category' => 'die', 'unit' => 'piece', 'average_cost' => 10]);
    $glue = InventoryItem::factory()->create(['type' => 'raw_material', 'category' => 'glue', 'unit' => 'kg', 'average_cost' => 10]);
    $data['services']['consumables'] = [['material_id' => $tool->id, 'quantity_per_basis' => 1, 'basis' => 'fixed'], ['material_id' => $glue->id, 'quantity_per_basis' => 0.0001, 'basis' => 'products']];
    array_unshift($data['services']['finishing'], ['name' => 'Die cut', 'machine_id' => $die->id, 'basis' => 'sheets', 'component_role' => 'stock']);
    expect(app(SheetfedCostCalculator::class)->calculate($data)->isComplete())->toBeTrue();
    $data['services']['components'][0]['blank_dimensions_confirmed'] = false;
    expect(app(SheetfedCostCalculator::class)->calculate($data)->isComplete())->toBeFalse();
});

it('renders and saves a complete workspace estimate with quantity comparisons', function (): void {
    $fixture = sheetfedFixture();
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $data = [...$fixture['data'], 'description' => 'Verified offset label'];
    $data['services']['comparison_quantities'] = '500,1000,2500';
    $page = Livewire::test(CreateCostEstimate::class)->fillForm($data);
    expect($page->instance()->estimatorResult->diagnostics)->toBe([])
        ->and($page->instance()->estimatorResult->total)->toBe(1066.17)
        ->and($page->instance()->estimatorComparisons)->toHaveCount(3);
    $page->call('create')->assertHasNoFormErrors();
    $estimate = CostEstimate::query()->firstOrFail();
    expect($estimate->formula_version)->toBe('sheetfed-v2')->and((float) $estimate->total)->toBe(1066.17);
});

it('renders finalized snapshots without recalculating from changed inventory', function (): void {
    $fixture = sheetfedFixture();
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $estimate = app(CostEstimateService::class)->finalize(CostEstimate::factory()->create($fixture['data']));
    $fixture['paper']->update(['average_cost' => 1000]);
    $page = Livewire::test(ViewCostEstimate::class, ['record' => $estimate->id]);
    expect($page->instance()->estimatorResult->total)->toBe(1066.17)
        ->and($page->instance()->estimatorResult->layouts[0]['parent_sheets'])->toBe(270);
});

it('costs hardcover text, case wrap, boards and endsheets with their required machines', function (): void {
    $fixture = sheetfedFixture();
    $fixture['press']->update(['setup_time_minutes' => 0, 'setup_waste' => 0]);
    $fixture['cutter']->update(['max_sheet_width_mm' => 100, 'max_sheet_height_mm' => 100]);
    $component = $fixture['data']['services']['components'][0];
    $board = $fixture['paper']->replicate();
    $board->fill(['sku' => 'HARD-BOARD', 'category' => 'board', 'printable_sides' => 0])->save();
    $glue = InventoryItem::factory()->create(['type' => 'raw_material', 'category' => 'glue', 'unit' => 'kg', 'average_cost' => 10]);
    $data = $fixture['data'];
    $data['job_type'] = 'books';
    $data['services']['product'] = ['width_mm' => 10, 'height_mm' => 20, 'page_count' => 16, 'binding' => 'hardcover', 'consumables_confirmed' => true];
    $data['services']['components'] = [
        [...$component, 'name' => 'Text', 'role' => 'text', 'signature_pages' => 16, 'front_colors' => 1, 'back_colors' => 1],
        [...$component, 'name' => 'Case wrap', 'role' => 'case', 'width_mm' => 40, 'height_mm' => 20, 'back_colors' => 0],
        [...$component, 'name' => 'Boards', 'role' => 'board', 'material_id' => $board->id, 'machine_id' => $fixture['cutter']->id, 'front_colors' => 0, 'back_colors' => 0, 'width_mm' => 30, 'height_mm' => 20, 'units_per_product' => 2],
        [...$component, 'name' => 'Endsheets', 'role' => 'endsheet', 'machine_id' => $fixture['cutter']->id, 'front_colors' => 0, 'back_colors' => 0, 'width_mm' => 40, 'height_mm' => 20, 'units_per_product' => 2],
    ];
    $data['services']['finishing'] = [];
    foreach (['folding', 'collating', 'case_making', 'hard_cover_gluing'] as $operation) {
        $machine = Machine::create(['name' => $operation, 'operation_type' => $operation, 'speed_unit' => $operation === 'folding' ? 'sheets_per_hour' : 'products_per_hour', 'hourly_cost' => 10, 'production_speed' => 1000, 'costing_configured' => true, 'is_active' => true, 'max_sheet_width_mm' => 100, 'max_sheet_height_mm' => 100]);
        $data['services']['finishing'][] = ['name' => $operation, 'machine_id' => $machine->id, 'basis' => $operation === 'folding' ? 'sheets' : 'products', 'component_role' => 'text'];
    }
    $data['services']['finishing'][] = ['name' => 'Trim', 'machine_id' => $fixture['cutter']->id, 'basis' => 'products'];
    $data['services']['consumables'] = [['material_id' => $glue->id, 'quantity_per_basis' => 1, 'basis' => 'fixed']];
    $result = app(SheetfedCostCalculator::class)->calculate($data);
    expect($result->diagnostics)->toBe([])->and(array_column($result->layouts, 'role'))->toBe(['text', 'case', 'board', 'endsheet'])
        ->and(collect($result->lines)->firstWhere('label', 'Boards')['quantity'])->toBe(134.0)
        ->and(collect($result->lines)->firstWhere('label', 'Endsheets')['quantity'])->toBe(200.0);
});

it('applies grain and image margins when recommending physically possible layouts', function (): void {
    $fixture = sheetfedFixture();
    $data = $fixture['data'];
    $data['services']['components'][0]['grain_axis'] = 'height';
    $fixture['press']->update(['gripper_margin_mm' => 20, 'side_margin_mm' => 10]);
    $result = app(SheetfedCostCalculator::class)->calculate($data);
    expect($result->layouts[0]['ups'])->toBe(4)->and($result->layouts[0]['usable_height_mm'])->toBe(80.0);
    $fixture['paper']->update(['grain_direction' => null]);
    expect(app(SheetfedCostCalculator::class)->calculate($data)->isComplete())->toBeFalse();
});

it('rejects an incompatible finishing basis and unconfigured machine rather than guessing', function (): void {
    $fixture = sheetfedFixture();
    $data = $fixture['data'];
    $data['services']['finishing'][0]['basis'] = 'sheets';
    $data['services']['finishing'][0]['component_role'] = 'stock';
    $fixture['press']->update(['costing_configured' => false]);
    $result = app(SheetfedCostCalculator::class)->calculate($data);
    expect(implode(' ', $result->diagnostics))->toContain('speed unit does not match')->toContain('costing configuration');
});

it('uses completed carton nesting with frozen geometry and rejects mismatched placement counts', function (): void {
    $fixture = sheetfedFixture();
    $die = Machine::create(['name' => 'Die cutter', 'operation_type' => 'die_cutting', 'speed_unit' => 'sheets_per_hour', 'hourly_cost' => 20, 'production_speed' => 1000, 'costing_configured' => true, 'is_active' => true, 'max_sheet_width_mm' => 100, 'max_sheet_height_mm' => 100]);
    $tool = InventoryItem::factory()->create(['type' => 'raw_material', 'category' => 'die', 'unit' => 'piece', 'average_cost' => 10]);
    $nest = DielineImposition::factory()->create([
        'status' => 'completed', 'paper_width' => 100, 'paper_height' => 100, 'copies' => 4, 'utilisation' => 64,
        'source_geometry' => ['bounds' => ['x' => 0, 'y' => 0, 'width' => 40, 'height' => 40], 'layers' => ['cut' => []]],
        'placements' => [['x' => 0, 'y' => 0, 'rotation' => 0], ['x' => 50, 'y' => 0, 'rotation' => 0], ['x' => 0, 'y' => 50, 'rotation' => 0], ['x' => 50, 'y' => 50, 'rotation' => 0]],
    ]);
    $data = $fixture['data'];
    $data['job_type'] = 'packages';
    $data['services']['product']['requires_gluing'] = false;
    $data['services']['components'][0]['dieline_imposition_id'] = $nest->id;
    $data['services']['components'][0]['width_mm'] = 1;
    $data['services']['components'][0]['height_mm'] = 1;
    $data['services']['finishing'] = [['machine_id' => $die->id, 'basis' => 'sheets', 'component_role' => 'stock']];
    $data['services']['consumables'] = [['material_id' => $tool->id, 'quantity_per_basis' => 1, 'basis' => 'fixed']];
    $result = app(SheetfedCostCalculator::class)->calculate($data);
    expect($result->diagnostics)->toBe([])->and($result->layouts[0]['imposition_id'])->toBe($nest->id)
        ->and($result->layouts[0]['ups'])->toBe(4)->and($result->layouts[0]['source_geometry']['bounds']['width'])->toBe(40);
    $nest->update(['copies' => 5]);
    expect(app(SheetfedCostCalculator::class)->calculate($data)->isComplete())->toBeFalse();
});

it('charges consumables only to their selected component and operation input quantity', function (): void {
    $fixture = sheetfedFixture();
    $fixture['cutter']->update(['max_sheet_width_mm' => 100, 'max_sheet_height_mm' => 100]);
    $laminator = Machine::create(['name' => 'Film laminator', 'operation_type' => 'laminating', 'speed_unit' => 'm2_per_hour', 'production_speed' => 100, 'hourly_cost' => 20, 'run_waste_percent' => 10, 'setup_waste' => 2, 'costing_configured' => true, 'is_active' => true, 'max_sheet_width_mm' => 100, 'max_sheet_height_mm' => 100]);
    $film = InventoryItem::factory()->create(['type' => 'raw_material', 'category' => 'lamination', 'unit' => 'm2', 'average_cost' => 10]);
    $data = $fixture['data'];
    $data['services']['components'][] = [...$data['services']['components'][0], 'role' => 'cover', 'name' => 'Unlaminated insert', 'machine_id' => $fixture['cutter']->id, 'front_colors' => 0, 'back_colors' => 0, 'width_mm' => 20, 'height_mm' => 20];
    array_unshift($data['services']['finishing'], ['machine_id' => $laminator->id, 'basis' => 'sheets', 'component_role' => 'stock']);
    $data['services']['consumables'] = [['material_id' => $film->id, 'basis' => 'm2', 'quantity_per_basis' => 1, 'component_role' => 'stock', 'operation_machine_id' => $laminator->id]];
    $result = app(SheetfedCostCalculator::class)->calculate($data);
    $filmLine = collect($result->lines)->firstWhere('inventory_item_id', $film->id);
    expect($result->diagnostics)->toBe([])->and($filmLine['quantity'])->toBe(2.8)->and($filmLine['total'])->toBe(28.0);
    $data['services']['consumables'][0]['component_role'] = 'cover';
    expect(app(SheetfedCostCalculator::class)->calculate($data)->isComplete())->toBeFalse();
});

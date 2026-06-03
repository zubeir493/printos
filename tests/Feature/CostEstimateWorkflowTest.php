<?php

use App\Filament\Resources\CostEstimates\Pages\CreateCostEstimate;
use App\Filament\Resources\InventoryItems\Pages\CreateInventoryItem;
use App\Models\CostEstimate;
use App\Models\InventoryItem;
use App\Models\Machine;
use App\Models\Partner;
use App\Models\Proforma;
use App\Models\Setting;
use App\Models\User;
use App\Services\Costing\CostEstimateService;
use App\Services\Costing\LabelCostCalculator;
use App\Services\Costing\PackageCostCalculator;
use App\UserRole;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('calculates a label estimate with inventory and VAT snapshots', function (): void {
    Setting::createDefault();

    $paper = InventoryItem::factory()->create([
        'name' => '100gsm Woodfree',
        'sku' => 'PAPER-100',
        'unit' => 'sheet',
        'type' => 'raw_material',
        'category' => 'paper',
        'average_cost' => 74,
        'gsm' => 100,
        'width' => 70,
        'height' => 100,
    ]);

    $result = app(LabelCostCalculator::class)->calculate([
        'quantity' => 5000,
        'services' => [
            'label' => [
                'width' => 20.7,
                'height' => 18.5,
                'colors' => 4,
                'yield' => 3,
                'waste_allowance_percent' => 0,
            ],
            'material' => [
                'material_item_id' => $paper->id,
            ],
            'production' => [
                'printing_up' => 3,
                'diecutting_up' => 1,
            ],
            'finishing' => [
                'cutting_unit_cost' => 25,
                'packing_unit_cost' => 20,
                'bundle_size' => 2000,
            ],
            'commercial' => [
                'overhead_percent' => 0,
                'profit_margin_percent' => 20,
                'discount_percent' => 0,
            ],
        ],
    ]);

    expect($result->subtotal)->toBeGreaterThan(0.0)
        ->and($result->vatRate)->toBe(15.0)
        ->and($result->taxAmount)->toBeGreaterThan(0.0)
        ->and($result->total)->toBe(round(($result->subtotal + $result->overheadAmount + $result->profitAmount - $result->discountAmount) + $result->taxAmount, 2))
        ->and($result->settingsSnapshot['vat_rate'])->toBe(15.0)
        ->and($result->materialConsumption[0]['inventory_item_id'])->toBe($paper->id)
        ->and($result->lines[0]['snapshot']['inventory']['gsm'])->toBe(100.0);

    $inkLine = collect($result->lines)->firstWhere('label', 'Ink consumption');

    expect($inkLine['quantity'])->toBeGreaterThan(4.0)
        ->and($inkLine['unit'])->toBe('ml');
});

it('calculates package material consumption from sheet layout', function (): void {
    Setting::createDefault();

    $board = InventoryItem::factory()->create([
        'name' => 'Food Grade 350gsm',
        'sku' => 'BOARD-350',
        'unit' => 'sheet',
        'type' => 'raw_material',
        'category' => 'board',
        'average_cost' => 60,
        'gsm' => 350,
    ]);

    $result = app(PackageCostCalculator::class)->calculate([
        'quantity' => 6000,
        'services' => [
            'box' => [
                'length' => 3.7,
                'width' => 3.7,
                'height' => 10.6,
                'colors' => 4,
                'print_coverage_percent' => 1.5,
            ],
            'layout' => [
                'ups' => 24,
                'waste_percent' => 1,
                'print_length' => 42,
                'print_width' => 70,
            ],
            'material' => [
                'board_item_id' => $board->id,
            ],
            'commercial' => [
                'overhead_percent' => 15,
                'profit_margin_percent' => 75,
            ],
        ],
    ]);

    expect($result->lines[0]['quantity'])->toBe(253.0)
        ->and($result->lines[0]['inventory_item_id'])->toBe($board->id)
        ->and($result->total)->toBeGreaterThan($result->subtotal);
});

it('normalizes purchase unit prices to base unit costs in estimates', function (): void {
    Setting::createDefault();

    $paper = InventoryItem::factory()->create([
        'name' => 'Ream Paper',
        'unit' => 'sheet',
        'purchase_unit' => 'ream',
        'conversion_factor' => 500,
        'type' => 'raw_material',
        'category' => 'paper',
        'average_cost' => 0,
        'price' => 1000,
    ]);

    $result = app(LabelCostCalculator::class)->calculate([
        'quantity' => 500,
        'services' => [
            'label' => [
                'width' => 10,
                'height' => 10,
                'colors' => 1,
                'yield' => 1,
                'waste_allowance_percent' => 0,
            ],
            'material' => [
                'material_item_id' => $paper->id,
            ],
            'production' => [
                'printing_up' => 1,
                'diecutting_up' => 1,
            ],
            'commercial' => [
                'overhead_percent' => 0,
                'profit_margin_percent' => 0,
                'discount_percent' => 0,
            ],
        ],
    ]);

    $stockLine = collect($result->lines)->firstWhere('label', 'Label stock');

    expect($stockLine['unit_cost'])->toBe(2.0)
        ->and($stockLine['total'])->toBe(1000.0)
        ->and($stockLine['snapshot']['inventory']['base_unit_cost'])->toBe(2.0)
        ->and($stockLine['snapshot']['inventory']['price_per_purchase_unit'])->toBe(1000.0);
});

it('creates a cost estimate from the Filament wizard', function (): void {
    Setting::createDefault();
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));

    $paper = InventoryItem::factory()->create([
        'name' => 'Sticker Sheet',
        'sku' => 'STICKER-SHEET',
        'unit' => 'sheet',
        'type' => 'raw_material',
        'category' => 'paper',
        'average_cost' => 10,
    ]);

    Livewire::test(CreateCostEstimate::class)
        ->fillForm([
            'description' => 'Tea bag label',
            'job_type' => 'labels',
            'quantity' => 5000,
            'services' => [
                'label' => [
                    'width' => 20,
                    'height' => 18,
                    'colors' => 4,
                    'yield' => 3,
                    'waste_allowance_percent' => 0,
                ],
                'material' => [
                    'material_item_id' => $paper->id,
                ],
                'production' => [
                    'printing_up' => 3,
                    'diecutting_up' => 1,
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $estimate = CostEstimate::query()->with('lines')->firstOrFail();

    expect($estimate->estimate_number)->toStartWith('CE-')
        ->and($estimate->partner_id)->toBeNull()
        ->and($estimate->lines)->not->toBeEmpty()
        ->and($estimate->subtotal)->toBeGreaterThan('0.00');
});

it('shows a waiting state in live summary until core estimate fields are filled', function (): void {
    Setting::createDefault();
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));

    Livewire::test(CreateCostEstimate::class)
        ->assertSee('Awaiting inputs')
        ->assertDontSee('Warnings');
});

it('requires customer data when converting an estimate to a proforma', function (): void {
    Setting::createDefault();

    $estimate = CostEstimate::factory()->create([
        'partner_id' => null,
        'description' => 'Label job',
        'quantity' => 5000,
        'job_type' => 'labels',
        'services' => [
            'label' => ['colors' => 4, 'yield' => 3],
            'production' => ['printing_up' => 3, 'diecutting_up' => 1],
        ],
    ]);

    expect(fn () => app(CostEstimateService::class)->createProforma($estimate))
        ->toThrow(ValidationException::class);
});

it('converts a finalized estimate to a proforma with customer and snapshots', function (): void {
    Setting::createDefault();

    $customer = Partner::factory()->create(['is_customer' => true]);
    $paper = InventoryItem::factory()->create([
        'name' => 'Label Stock',
        'sku' => 'LABEL-STOCK',
        'unit' => 'sheet',
        'type' => 'raw_material',
        'category' => 'paper',
        'average_cost' => 8,
    ]);

    $estimate = CostEstimate::factory()->create([
        'partner_id' => null,
        'description' => 'Label job',
        'quantity' => 5000,
        'job_type' => 'labels',
        'services' => [
            'label' => ['colors' => 4, 'yield' => 3],
            'material' => ['material_item_id' => $paper->id],
            'production' => ['printing_up' => 3, 'diecutting_up' => 1],
        ],
    ]);

    $proforma = app(CostEstimateService::class)->createProforma($estimate, [
        'partner_id' => $customer->id,
        'issue_date' => now()->toDateString(),
        'expiry_date' => now()->addDays(15)->toDateString(),
    ]);

    expect($estimate->refresh()->status)->toBe('converted')
        ->and($proforma)->toBeInstanceOf(Proforma::class)
        ->and($proforma->cost_estimate_id)->toBe($estimate->id)
        ->and($proforma->partner_id)->toBe($customer->id)
        ->and($proforma->tasks)->toHaveCount(1)
        ->and($proforma->tasks->first()->cost_breakdown)->not->toBeEmpty()
        ->and((float) $proforma->tasks->first()->rate_snapshot['vat_rate'])->toBe(15.0);
});

it('preserves finalized estimate totals after source rates change', function (): void {
    Setting::createDefault();

    $paper = InventoryItem::factory()->create([
        'name' => 'Historical Paper',
        'sku' => 'HIST-PAPER',
        'unit' => 'sheet',
        'type' => 'raw_material',
        'category' => 'paper',
        'average_cost' => 10,
    ]);

    $estimate = CostEstimate::factory()->create([
        'job_type' => 'labels',
        'quantity' => 1000,
        'services' => [
            'label' => ['colors' => 1, 'yield' => 1],
            'material' => ['material_item_id' => $paper->id],
            'production' => ['printing_up' => 1, 'diecutting_up' => 1],
        ],
    ]);

    app(CostEstimateService::class)->finalize($estimate);
    $finalizedTotal = $estimate->refresh()->total;

    $paper->update(['average_cost' => 999]);
    Setting::first()->update(['vat_rate' => 30]);

    expect($estimate->refresh()->total)->toEqual($finalizedTotal)
        ->and((float) $estimate->settings_snapshot['vat_rate'])->toBe(15.0);
});

it('uses selected machine rates in label calculations', function (): void {
    Setting::createDefault()->update([
        'costing_defaults' => [
            'plate_unit_cost' => 1000,
            'make_ready_unit_cost' => 25,
            'label_diecutting_unit_cost' => 0,
            'label_cutting_unit_cost' => 25,
            'overhead_percent' => 15,
            'profit_margin_percent' => 20,
            'waste_percent' => 3,
        ],
    ]);

    $machine = Machine::create([
        'name' => 'Flexo 1',
        'code' => 'FLX-1',
        'operation_type' => 'printing',
        'baseline_rounds_per_week' => 0,
        'production_speed' => 500,
        'hourly_cost' => 100,
    ]);

    $result = app(LabelCostCalculator::class)->calculate([
        'quantity' => 5000,
        'services' => [
            'label' => ['colors' => 4, 'yield' => 1, 'waste_allowance_percent' => 0],
            'production' => ['machine_id' => $machine->id, 'printing_up' => 2, 'diecutting_up' => 1],
            'commercial' => ['overhead_percent' => 0, 'profit_margin_percent' => 0],
        ],
    ]);

    $printing = collect($result->lines)->firstWhere('label', 'Printing');
    $makeReady = collect($result->lines)->firstWhere('label', 'Make ready');

    expect($printing['quantity'])->toBe(5.0)
        ->and($printing['unit_cost'])->toBe(100.0)
        ->and($makeReady['unit_cost'])->toBe(25.0)
        ->and($printing['snapshot']['machine']['production_speed'])->toBe(500.0);
});

it('shows paper metadata fields only for paper-like inventory categories', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));

    Livewire::test(CreateInventoryItem::class)
        ->fillForm([
            'type' => 'raw_material',
            'category' => 'paper',
        ])
        ->assertFormFieldIsVisible('gsm')
        ->fillForm([
            'type' => 'raw_material',
            'category' => 'ink',
        ])
        ->assertFormFieldIsHidden('gsm')
        ->assertFormFieldIsVisible('default_waste_percent');
});

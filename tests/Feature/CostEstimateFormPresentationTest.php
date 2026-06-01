<?php

use App\Filament\Resources\CostEstimates\Pages\CreateCostEstimate;
use App\Models\InventoryItem;
use App\Models\Machine;
use App\Models\Setting;
use App\Models\User;
use App\UserRole;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Setting::createDefault();
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
});

it('renders the live summary as a styled estimator panel', function (): void {
    Livewire::test(CreateCostEstimate::class)
        ->assertSee('Selling Price')
        ->assertSee('Unit Price')
        ->assertSee('Waiting for required fields')
        ->assertSee('Complete product specs and material selection to calculate.')
        ->assertSeeHtml('cost-summary-metric cost-summary-total')
        ->assertSeeHtml('cost-summary-alert');
});

it('renders inventory and machine snapshots with structured costing details', function (): void {
    $paper = InventoryItem::factory()->create([
        'name' => 'Premium Label Stock',
        'unit' => 'sheet',
        'type' => 'raw_material',
        'category' => 'paper',
        'average_cost' => 12.5,
        'gsm' => 120,
        'width' => 70,
        'height' => 100,
    ]);

    $machine = Machine::create([
        'name' => 'Flexo Press',
        'code' => 'FLX-2',
        'operation_type' => 'printing',
        'baseline_rounds_per_week' => 0,
        'production_speed' => 500,
        'hourly_cost' => 125,
    ]);

    Livewire::test(CreateCostEstimate::class)
        ->fillForm([
            'description' => 'Bottle label',
            'job_type' => 'labels',
            'quantity' => 1000,
            'services' => [
                'label' => [
                    'width' => 10,
                    'height' => 8,
                    'yield' => 2,
                ],
                'material' => [
                    'material_item_id' => $paper->id,
                ],
                'production' => [
                    'machine_id' => $machine->id,
                    'printing_up' => 2,
                    'diecutting_up' => 1,
                ],
            ],
        ])
        ->assertSee('Premium Label Stock')
        ->assertSee('12.50 Birr/sheet')
        ->assertSee('GSM')
        ->assertSee('Stock')
        ->assertSee('Flexo Press')
        ->assertSee('500.00 units/hr')
        ->assertSee('Hourly cost')
        ->assertSeeHtml('cost-snapshot-card cost-snapshot-card-inventory')
        ->assertSeeHtml('cost-snapshot-card cost-snapshot-card-machine');
});

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
        ->assertSee('Final Price')
        ->assertSee('Unit Price')
        ->assertDontSee('Material Consumption')
        ->assertSee('Awaiting inputs')
        ->assertDontSee('Warnings')
        ->assertSeeHtml('cost-summary-metric cost-summary-total')
        ->assertDontSeeHtml('cost-summary-alert');
});

it('renders inventory and machine snapshots with structured costing details', function (): void {
    Setting::first()->update(['vat_rate' => 14]);

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
        ->goToWizardStep(3)
        ->assertSee('Material Consumption')
        ->assertSee('Premium Label Stock')
        ->assertSee('Profit Margin (20%)')
        ->assertSee('VAT (14%)')
        ->assertSeeHtml('x-tooltip')
        ->assertSee('120 gsm')
        ->goToWizardStep(4)
        ->assertSee('Flexo Press')
        ->assertSee('Machine Rates Snapshot');
});

it('shows material consumption when a material-backed estimate is calculable', function (): void {
    $paper = InventoryItem::factory()->create([
        'name' => 'Premium Label Stock',
        'unit' => 'sheet',
        'type' => 'raw_material',
        'category' => 'paper',
        'average_cost' => 12.5,
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
                ],
                'material' => [
                    'material_item_id' => $paper->id,
                ],
            ],
        ])
        ->assertSee('Material Consumption')
        ->assertSee('Premium Label Stock')
        ->assertDontSee('Label stock')
        ->assertSeeHtml('cost-summary-list');
});

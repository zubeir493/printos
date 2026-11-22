<?php

use App\Filament\Resources\CostEstimates\CostEstimateResource;
use App\Filament\Resources\CostEstimates\Pages\CreateCostEstimate;
use App\Filament\Resources\CostEstimates\Pages\EditCostEstimate;
use App\Filament\Resources\CostEstimates\Pages\ViewCostEstimate;
use App\Filament\Resources\Machines\Pages\CreateMachine;
use App\Models\CostEstimate;
use App\Models\Machine;
use App\Models\Setting;
use App\Models\User;
use App\UserRole;
use Database\Seeders\CostingDemoSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Setting::createDefault();
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
});

it('presents the product configurator with honest incomplete pricing and accessible production details', function (): void {
    Livewire::test(CreateCostEstimate::class)
        ->assertSee('What are we printing?')->assertSee('Material &amp; layout', escape: false)
        ->assertSee('Production route')->assertSee('Pricing')->assertSee('Quantities')
        ->assertSee('Awaiting review')->assertSee('Known costs so far')->assertSee('Machine rates include shop overhead.')
        ->assertDontSee('Calculated machine time')
        ->set('estimatorTab', 'materials')->assertFormFieldExists('materials.services.components')
        ->set('estimatorTab', 'production')->assertFormFieldExists('production.services.finishing')
        ->assertSeeHtml('fi-fo-table-repeater')
        ->assertFormSet(['services.costing_version' => 'sheetfed-v2']);
});

it('builds appropriate component and finishing templates for each book binding', function (string $binding, array $roles): void {
    $page = Livewire::test(CreateCostEstimate::class)->call('applyEstimatorTemplate', 'books', $binding);
    $components = $page->get('data.services.components');
    expect(array_column(array_values($components), 'role'))->toBe($roles);
    expect($page->get('data.services.product.consumables_confirmed'))->toBeFalse();
})->with([
    ['saddle', ['text', 'cover']], ['perfect', ['text', 'cover']], ['hardcover', ['text', 'case', 'board', 'endsheet']],
]);

it('saves verified machine setup and sheet capabilities from the resource', function (): void {
    Livewire::test(CreateMachine::class)->fillForm([
        'name' => 'Configured offset press', 'operation_type' => 'printing', 'hourly_cost' => 100,
        'production_speed' => 2500, 'speed_unit' => 'sheets_per_hour', 'color_units' => 4,
        'max_sheet_width_mm' => 520, 'max_sheet_height_mm' => 740, 'setup_time_minutes' => 20,
        'setup_waste' => 40, 'run_waste_percent' => 2, 'costing_configured' => true,
    ])->call('create')->assertHasNoFormErrors();
    $machine = Machine::query()->where('name', 'Configured offset press')->firstOrFail();
    expect($machine->costing_configured)->toBeTrue()->and((float) $machine->setup_waste)->toBe(40.0)
        ->and((float) $machine->max_sheet_width_mm)->toBe(520.0)->and($machine->speed_unit)->toBe('sheets_per_hour');
});

it('keeps costing inaccessible in a panel without financial access', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('warehouse'));
    expect(CostEstimateResource::canAccess())->toBeFalse()->and(CostEstimateResource::canCreate())->toBeFalse();
});

it('applies row options and preserves inactive panels when saving a draft', function (): void {
    $this->seed(CostingDemoSeeder::class);
    $estimate = CostEstimate::where('estimate_number', 'DEMO-CE-PERFECT')->firstOrFail();
    $page = Livewire::test(EditCostEstimate::class, ['record' => $estimate->id])->set('estimatorTab', 'production');
    $repeater = collect($page->instance()->form->getFlatComponents(withHidden: true))
        ->first(fn ($component): bool => $component instanceof Repeater && $component->getName() === 'services.finishing');
    $rowKey = array_key_first($page->get('data.services.finishing'));
    $action = TestAction::make('operationOptions')->schemaComponent($repeater->getKey(), schema: '')->arguments(['item' => $rowKey]);
    $page->mountAction($action)->assertActionMounted($action)->setActionData([
        'name' => 'Calibrated folding', 'hourly_rate_override' => 300, 'speed_override' => 2000,
        'machine_override_reason' => 'Tested production rate',
    ])->callMountedAction()->assertHasNoActionErrors();
    expect($page->get('data.services.finishing')[$rowKey]['name'])->toBe('Calibrated folding');
    $consumableRepeater = collect($page->instance()->form->getFlatComponents(withHidden: true))
        ->first(fn ($component): bool => $component instanceof Repeater && $component->getName() === 'services.consumables');
    $consumableKey = array_key_first($page->get('data.services.consumables'));
    $page->callAction(TestAction::make('consumableOptions')->schemaComponent($consumableRepeater->getKey(), schema: '')->arguments(['item' => $consumableKey]), data: [
        'unit_cost_override' => 400, 'override_reason' => 'Supplier test rate',
    ])->assertHasNoActionErrors();
    $page->set('estimatorTab', 'materials');
    $componentRepeater = collect($page->instance()->form->getFlatComponents(withHidden: true))
        ->first(fn ($component): bool => $component instanceof Repeater && $component->getName() === 'services.components');
    $componentKey = array_key_first($page->get('data.services.components'));
    $page->callAction(TestAction::make('componentOptions')->schemaComponent($componentRepeater->getKey(), schema: '')->arguments(['item' => $componentKey]), data: [
        'unit_cost_override' => 9, 'override_reason' => 'Sample material quote',
    ])->assertHasNoActionErrors();
    expect($page->get('data.services.components')[$componentKey]['layout_confirmed'])->toBeFalse();
    $components = array_values($page->get('data.services.components'));
    $consumables = array_values($page->get('data.services.consumables'));
    $page->set('estimatorTab', 'specifications')->call('save')->assertHasNoFormErrors();
    $saved = $estimate->refresh()->services;
    expect($saved['finishing'][0]['name'])->toBe('Calibrated folding')
        ->and((float) $saved['finishing'][0]['hourly_rate_override'])->toBe(300.0)
        ->and($saved['finishing'][0]['machine_override_reason'])->toBe('Tested production rate')
        ->and((float) $saved['components'][0]['unit_cost_override'])->toBe(9.0)
        ->and($saved['components'][0]['override_reason'])->toBe('Sample material quote')
        ->and((float) $saved['consumables'][0]['unit_cost_override'])->toBe(400.0)
        ->and($saved['components'])->toHaveCount(count($components))
        ->and($saved['consumables'])->toEqual($consumables);
    foreach ($components as $index => $component) {
        if ($component['role'] !== 'text') {
            unset($component['signature_pages']);
        } else {
            unset($component['width_mm'], $component['height_mm']);
        }
        expect($saved['components'][$index])->toMatchArray($component);
    }
});

it('keeps row modification actions disabled on the read only estimate', function (): void {
    $this->seed(CostingDemoSeeder::class);
    $estimate = CostEstimate::where('estimate_number', 'DEMO-CE-PERFECT')->firstOrFail();
    $page = Livewire::test(ViewCostEstimate::class, ['record' => $estimate->id]);
    $repeaters = collect($page->instance()->form->getFlatComponents(withHidden: true))
        ->filter(fn ($component): bool => $component instanceof Repeater);
    foreach ($repeaters as $repeater) {
        foreach ($repeater->getExtraItemActions() as $action) {
            expect($action->isDisabled())->toBeTrue();
        }
    }
    expect($repeaters)->toHaveCount(3);
});

<?php

use App\Filament\Resources\CostEstimates\Pages\CreateCostEstimate;
use App\Filament\Resources\CostEstimates\Pages\EditCostEstimate;
use App\Filament\Resources\CostEstimates\Pages\ViewCostEstimate;
use App\Models\CostEstimate;
use App\Models\InventoryItem;
use App\Models\Machine;
use App\Models\Setting;
use App\Models\User;
use App\Services\Costing\EstimatorRecipe;
use App\Services\Costing\SheetfedCostCalculator;
use App\UserRole;
use Database\Seeders\CostingDemoSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Setting::createDefault();
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
});

it('shows a single save action and presents pricing before the product fields', function (): void {
    $page = Livewire::test(CreateCostEstimate::class);
    $html = $page->html();

    expect(substr_count($html, 'Save estimate'))->toBe(1)
        ->and($html)->not->toContain('<main class="estimator-config-main">');
    expect(strpos($html, 'Live estimate'))->toBeLessThan(strpos($html, 'What are we printing?'));

    $page->set('estimatorTab', 'materials')->assertSee('Save estimate');
    expect(substr_count($page->html(), 'Save estimate'))->toBe(1);
});

it('starts each product from a compact configurator with its required components', function (string $key, string $family, array $roles): void {
    $page = Livewire::test(CreateCostEstimate::class)->call('selectEstimatorProduct', $key)
        ->assertSet('estimatorTab', 'configure')->assertSet('data.job_type', $family)
        ->assertSee('What are we printing?')->assertSee('Production details')->assertDontSee('Calculated machine time');
    expect(array_column(array_values($page->get('data.services.components')), 'role'))->toBe($roles);
})->with([
    ['booklet', 'books', ['text', 'cover']], ['paperback', 'books', ['text', 'cover']],
    ['hardcover', 'books', ['text', 'case', 'board', 'endsheet']], ['labels', 'labels', ['stock']],
    ['stickers', 'stickers', ['stock']], ['carton', 'packages', ['stock']],
]);

it('preserves manual selections zero overrides custom rows and pricing across recipe changes', function (): void {
    $this->seed(CostingDemoSeeder::class);
    $estimate = CostEstimate::where('estimate_number', 'DEMO-CE-PERFECT')->firstOrFail();
    $data = $estimate->toArray();
    $data['services']['components'][0]['unit_cost_override'] = 0;
    $data['services']['components'][0]['override_reason'] = 'Customer supplied paper';
    $data['services']['finishing'][] = ['name' => 'Custom packing', 'machine_id' => Machine::where('code', 'DEMO-COST-PACKING')->value('id'), 'basis' => 'products', 'hourly_rate_override' => 333, 'machine_override_reason' => 'Actual rate'];
    $recipe = app(EstimatorRecipe::class);
    $changed = $recipe->apply($data, 'booklet');
    expect($changed['services']['components'][0]['material_id'])->toBe($data['services']['components'][0]['material_id'])
        ->and($changed['services']['components'][0]['unit_cost_override'])->toBe(0)
        ->and($changed['services']['components'][0]['layout_confirmed'])->toBeFalse()
        ->and($changed['services']['commercial'])->toBe($data['services']['commercial'])
        ->and(collect($changed['services']['finishing'])->firstWhere('name', 'Custom packing')['hourly_rate_override'])->toBe(333)
        ->and($recipe->apply($changed, 'booklet'))->toBe($changed);
    $result = app(SheetfedCostCalculator::class)->calculate($changed);
    expect($result->isComplete())->toBeFalse()
        ->and(implode(' ', $result->diagnostics))->toContain('retained perfect binding');
});

it('saves a complete estimate from the simplified screen without dropping advanced state', function (): void {
    $this->seed(CostingDemoSeeder::class);
    $estimate = CostEstimate::where('estimate_number', 'DEMO-CE-PERFECT')->firstOrFail();
    $page = Livewire::test(EditCostEstimate::class, ['record' => $estimate->id]);
    $before = $page->get('data.services');
    $page->set('data.quantity', 1500)->set('data.services.commercial.profit_percent', 30)
        ->call('save')->assertHasNoFormErrors();
    $saved = $estimate->refresh();
    expect($saved->quantity)->toBe(1500)->and((float) $saved->services['commercial']['profit_percent'])->toBe(30.0)
        ->and($saved->services['consumables'])->toEqual(array_values($before['consumables']))
        ->and($saved->services['components'][0]['layout_confirmed'])->toBeTrue()
        ->and((int) $saved->services['components'][0]['material_id'])->toBe((int) array_values($before['components'])[0]['material_id'])
        ->and($saved->total)->toBeGreaterThan(0);
});

it('invalidates verified layouts when finished geometry changes and keeps label stock in sync', function (): void {
    $this->seed(CostingDemoSeeder::class);
    $estimate = CostEstimate::where('estimate_number', 'DEMO-CE-LABELS')->firstOrFail();
    $page = Livewire::test(EditCostEstimate::class, ['record' => $estimate->id])->set('data.services.product.width_mm', 70);
    $row = array_values($page->get('data.services.components'))[0];
    expect($row['layout_confirmed'])->toBeFalse()->and((float) $row['width_mm'])->toBe(70.0)
        ->and($row['layout_key'])->toBeNull()->and($page->instance()->estimatorResult->isComplete())->toBeFalse();
});

it('prepares a single compatible press plate and ink when choosing paper', function (): void {
    $this->seed(CostingDemoSeeder::class);
    $page = Livewire::test(CreateCostEstimate::class)->call('selectEstimatorProduct', 'labels');
    $key = array_key_first($page->get('data.services.components'));
    $page->set('data.services.components.'.$key.'.material_id', InventoryItem::where('sku', 'DEMO-COST-TEXT')->value('id'));
    $row = $page->get('data.services.components')[$key];
    expect($row['machine_id'])->toBe(Machine::where('code', 'DEMO-COST-PRINTING')->value('id'))
        ->and($row['ink_item_id'])->toBe(InventoryItem::where('sku', 'DEMO-COST-INK')->value('id'))
        ->and($row['plate_item_id'])->toBe(InventoryItem::where('sku', 'DEMO-COST-PLATE')->value('id'))
        ->and($row['layout_confirmed'])->toBeFalse();
});

it('prepares lamination with the correct cover target and square metre film consumption', function (): void {
    $this->seed(CostingDemoSeeder::class);
    $estimate = CostEstimate::where('estimate_number', 'DEMO-CE-PERFECT')->firstOrFail();
    $laminator = Machine::where('code', 'DEMO-COST-LAMINATING')->firstOrFail();
    $page = Livewire::test(EditCostEstimate::class, ['record' => $estimate->id]);
    $route = array_filter($page->get('data.services.finishing'), fn (array $row): bool => (int) ($row['machine_id'] ?? 0) !== $laminator->id);
    $page->set('data.services.finishing', $route)->call('addEstimatorFinish', 'laminating');
    $operation = array_values($page->get('data.services.finishing'));
    $added = collect($operation)->last(fn (array $row): bool => ($row['machine_id'] ?? null) === $laminator->id);
    expect($added['basis'])->toBe('sheets')->and($added['component_role'])->toBe('cover')
        ->and($page->get('data.services.product.consumables_confirmed'))->toBeFalse();
    $film = collect($page->get('data.services.consumables'))->firstWhere('operation_machine_id', $laminator->id);
    expect($film['basis'])->toBe('m2')->and((float) $film['quantity_per_basis'])->toBe(1.0)
        ->and($film['component_role'])->toBe('cover');
    foreach ($page->get('data.services.components') as $row) {
        expect($row['layout_confirmed'])->toBeFalse();
    }
    $before = $page->get('data.services');
    $page->call('addEstimatorFinish', 'laminating');
    expect($page->get('data.services'))->toBe($before);
});

it('does not guess ambiguous machines or unknown consumption rates', function (): void {
    $this->seed(CostingDemoSeeder::class);
    $press = Machine::where('code', 'DEMO-COST-PRINTING')->firstOrFail();
    $copy = $press->replicate();
    $copy->code = 'ANOTHER-PRESS';
    $copy->save();
    app()->forgetScopedInstances();
    $recipe = app(EstimatorRecipe::class);
    $component = $recipe->prepareComponent(['material_id' => InventoryItem::where('sku', 'DEMO-COST-TEXT')->value('id'), 'front_colors' => 4], ['width_mm' => 50, 'height_mm' => 30], 'labels');
    expect($component['machine_id'] ?? null)->toBeNull();
    $book = $recipe->apply(['job_type' => 'labels'], 'paperback');
    expect(array_values($book['services']['consumables'])[0]['quantity_per_basis'])->toBeNull();
});

it('places a new sheet finish before cutting the same component', function (): void {
    $this->seed(CostingDemoSeeder::class);
    $estimate = CostEstimate::where('estimate_number', 'DEMO-CE-LABELS')->firstOrFail();
    $prepared = app(EstimatorRecipe::class)->addFinish($estimate->toArray(), 'laminating');
    $route = array_values($prepared['services']['finishing']);
    expect(Machine::findOrFail($route[0]['machine_id'])->operation_type)->toBe('laminating')
        ->and(Machine::findOrFail($route[1]['machine_id'])->operation_type)->toBe('cutting');
});

it('rejects product and finishing mutations from a read only page', function (): void {
    $this->seed(CostingDemoSeeder::class);
    $estimate = CostEstimate::where('estimate_number', 'DEMO-CE-PERFECT')->firstOrFail();
    $page = Livewire::test(ViewCostEstimate::class, ['record' => $estimate->id]);
    expect($page->instance()->estimatorCanEdit())->toBeFalse();
    expect(fn () => $page->instance()->selectEstimatorProduct('hardcover'))->toThrow(HttpException::class);
    expect(fn () => $page->instance()->addEstimatorFinish('laminating'))->toThrow(HttpException::class);
});

it('routes review actions to the relevant detailed controls', function (): void {
    $page = Livewire::test(CreateCostEstimate::class)->instance();
    expect($page->estimatorIssueTarget('Add binding glue consumption from inventory.'))->toBe('production')
        ->and($page->estimatorIssueTarget('Book text: review and confirm the sheet layout.'))->toBe('materials')
        ->and($page->estimatorIssueTarget('Margin must be less than 100%.'))->toBe('pricing');
});

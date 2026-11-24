<?php

use App\Filament\Resources\CostEstimates\Pages\EditCostEstimate;
use App\Models\CostEstimate;
use App\Models\InventoryItem;
use App\Models\User;
use App\Services\Costing\EstimatorRequestContext;
use App\Services\Costing\SheetfedCostCalculator;
use App\UserRole;
use Database\Seeders\CostingDemoSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('defers detail panels until opened without repeated database work', function (): void {
    $this->seed(CostingDemoSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $estimate = CostEstimate::where('estimate_number', 'DEMO-CE-HARDCOVER')->firstOrFail();
    app()->forgetScopedInstances();
    DB::enableQueryLog();
    DB::flushQueryLog();
    $started = microtime(true);
    $page = Livewire::test(EditCostEstimate::class, ['record' => $estimate->id]);
    $html = $page->html();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    $count = preg_match_all('/<(?:input|select|textarea)\b/', $html);
    fwrite(STDOUT, json_encode(['estimator_profile' => ['milliseconds' => round((microtime(true) - $started) * 1000), 'queries' => count($queries), 'controls' => $count, 'html_bytes' => strlen($html)]]).PHP_EOL);
    $page->assertSee('Text pages (excludes cover)');
    expect($page->instance()->estimatorResult->isComplete())->toBeTrue()
        ->and(count($queries))->toBeLessThan(25)->and($count)->toBeLessThan(25)
        ->and(strlen($html))->toBeLessThan(200000);
    $page->assertDontSee('Calculated machine time')->assertSeeHtml('fi-sc-loading');
    $wizard = $page->instance()->form->getComponent('estimator-wizard');
    $production = $wizard->getChildSchema()->getComponent('production')->getChildSchema()->getKey();
    $materials = $wizard->getChildSchema()->getComponent('materials')->getChildSchema()->getKey();

    $page->call('loadLazySchema', $production)->call('$refresh')
        ->assertSeeHtml('fi-fo-table-repeater')->assertSee('Calculated machine time');
    $page->call('loadLazySchema', $materials)->call('$refresh')
        ->assertSee('I have verified this layout, grain, folding and finishing allowances');
});

it('reuses selected sources for quantity previews and refreshes rates for authoritative calculations', function (): void {
    $this->seed(CostingDemoSeeder::class);
    $estimate = CostEstimate::where('estimate_number', 'DEMO-CE-PERFECT')->firstOrFail();
    $context = app(EstimatorRequestContext::class);
    $context->refresh();
    $calculator = app(SheetfedCostCalculator::class);
    $first = $calculator->calculate($estimate->toArray(), fresh: false);
    DB::enableQueryLog();
    DB::flushQueryLog();
    $alternative = $calculator->calculate([...$estimate->toArray(), 'quantity' => 2000], fresh: false);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    expect($queries)->toBe([])->and($alternative->total)->toBeGreaterThan($first->total);
    InventoryItem::where('sku', 'DEMO-COST-TEXT')->update(['average_cost' => 100]);
    $fresh = $calculator->calculate($estimate->toArray());
    expect($fresh->subtotal)->toBeGreaterThan($first->subtotal);
    app()->forgetScopedInstances();
    expect(app(EstimatorRequestContext::class))->not->toBe($context);
});

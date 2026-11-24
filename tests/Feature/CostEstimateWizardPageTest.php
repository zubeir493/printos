<?php

use App\Filament\Resources\CostEstimates\Pages\CreateCostEstimate;
use App\Filament\Resources\CostEstimates\Pages\EditCostEstimate;
use App\Models\CostEstimate;
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

it('renders the create estimator in the standard Filament form', function (): void {
    Livewire::test(CreateCostEstimate::class)
        ->assertSee('Product family')
        ->assertSee('Save estimate')
        ->assertSee('Cancel')
        ->assertSeeHtml('estimator-wizard')
        ->assertDontSeeHtml('estimator-canvas-shell')
        ->assertDontSeeHtml('estimator-details-modal');
});

it('uses the standard edit form and keeps its estimate actions visible', function (): void {
    Livewire::test(EditCostEstimate::class, ['record' => CostEstimate::factory()->create(['formula_version' => 'sheetfed-v2'])->id])
        ->assertSee('Finalize')
        ->assertSee('Create Proforma')
        ->assertSee('Save estimate')
        ->assertSee('Cancel')
        ->assertSeeHtml('estimator-wizard')
        ->assertDontSeeHtml('estimator-canvas-shell');
});

it('opens the step containing a validation error when saving', function (): void {
    $page = Livewire::test(CreateCostEstimate::class)
        ->set('data.description', 'Wizard validation')
        ->set('data.services.comparison_quantities', 'invalid')
        ->call('create')
        ->assertHasFormErrors(['services.comparison_quantities' => 'regex']);

    $wizard = $page->instance()->form->getComponent('estimator-wizard');
    $quantities = $wizard->getChildSchema()->getComponent('quantities');
    $page->assertDispatched('go-to-wizard-step', key: $wizard->getKey(), step: $quantities->getKey());
});

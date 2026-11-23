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

it('renders the create estimator as a full-screen workspace with a back link', function (): void {
    Livewire::test(CreateCostEstimate::class)
        ->assertSee('Back to estimates')
        ->assertSee('Change product')
        ->assertSeeHtml('estimator-canvas-shell')
        ->assertSeeHtml('estimator-config-sidebar')
        ->assertDontSeeHtml('estimator-canvas-toolbar')
        ->assertDontSee('Cancel');
});

it('renders the edit estimator full-screen and keeps its estimate actions visible', function (): void {
    Livewire::test(EditCostEstimate::class, ['record' => CostEstimate::factory()->create(['formula_version' => 'sheetfed-v2'])->id])
        ->assertSee('Back to estimate')
        ->assertSee('Finalize')
        ->assertSee('Create Proforma')
        ->assertSeeHtml('estimator-canvas-shell')
        ->assertDontSeeHtml('estimator-canvas-toolbar')
        ->assertDontSee('Cancel');
});

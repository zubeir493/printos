<?php

use App\Filament\Resources\JobOrders\JobOrderResource;
use App\Filament\Resources\JobOrders\RelationManagers\JobOrderArtworksRelationManager;
use App\Filament\Resources\JobOrders\RelationManagers\MaterialsOverviewRelationManager;
use App\Filament\Resources\JobOrders\RelationManagers\PaymentsRelationManager;
use App\Filament\Support\RelationManagerToolbarTabs;
use App\Models\User;
use App\UserRole;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('relation manager tabs render in the table toolbar with parent tab switching', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));

    $tabs = RelationManagerToolbarTabs::tabsForResource(JobOrderResource::class);

    expect($tabs)
        ->toHaveCount(3)
        ->and(array_column($tabs, 'manager'))->toBe([
            MaterialsOverviewRelationManager::class,
            JobOrderArtworksRelationManager::class,
            PaymentsRelationManager::class,
        ]);

    $html = view('filament.tables.relation-manager-toolbar-tabs', [
        'activeManager' => JobOrderArtworksRelationManager::class,
        'tabs' => $tabs,
    ])->render();

    expect($html)
        ->toContain('relation-manager-toolbar-tabs')
        ->toContain('Related records')
        ->toContain("\$wire.\$parent.\$set('activeRelationManager', '0')")
        ->toContain("\$wire.\$parent.\$set('activeRelationManager', '1')")
        ->toContain("\$wire.\$parent.\$set('activeRelationManager', '2')");
});

test('relation manager toolbar tabs are omitted for resources with one visible relation', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));
    $this->actingAs(User::factory()->create(['role' => UserRole::Finance]));

    $html = view('filament.tables.relation-manager-toolbar-tabs', [
        'activeManager' => App\Filament\Resources\SalesOrders\RelationManagers\PaymentsRelationManager::class,
    ])->render();

    expect($html)->not->toContain('relation-manager-toolbar-tabs');
});

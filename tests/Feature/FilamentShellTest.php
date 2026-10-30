<?php

use App\Filament\Resources\CostEstimates\CostEstimateResource;
use App\Models\User;
use App\UserRole;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('cost estimates are absent from navigation in every panel', function (string $panelId): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    Filament::setCurrentPanel(Filament::getPanel($panelId));

    $navigationUrls = collect(Filament::getNavigation())
        ->flatMap(fn ($group) => $group->getItems())
        ->map(fn ($item) => $item->getUrl());

    expect(CostEstimateResource::shouldRegisterNavigation())->toBeFalse()
        ->and($navigationUrls->filter(fn (?string $url): bool => str_contains($url ?? '', '/cost-estimates')))->toBeEmpty();
})->with(['admin', 'design', 'finance', 'hr', 'operations', 'production', 'retail', 'sales', 'typist', 'warehouse']);

test('filament page titles append the brand name once', function (string $uri, string $expectedTitle): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));

    $response = $this->get($uri);

    $response->assertSuccessful();

    preg_match('/<title>\s*(.*?)\s*<\/title>/s', $response->getContent(), $matches);

    $title = preg_replace('/\s+/', ' ', trim($matches[1] ?? ''));

    expect($title)->toBe($expectedTitle)
        ->and(substr_count($title, 'Packledge'))->toBe(1);
})->with([
    'dashboard' => ['/', 'Dashboard - Packledge'],
    'cost estimates' => ['/cost-estimates', 'Cost Estimates - Packledge'],
]);

test('filament panels use spa navigation for page loading feedback', function (string $provider): void {
    expect(file_get_contents(app_path("Providers/Filament/{$provider}")))
        ->toContain('->spa()');
})->with([
    'AdminPanelProvider.php',
    'DesignPanelProvider.php',
    'FinancePanelProvider.php',
    'HrPanelProvider.php',
    'OperationsPanelProvider.php',
    'ProductionPanelProvider.php',
    'RetailPanelProvider.php',
    'SalesPanelProvider.php',
    'TypistPanelProvider.php',
    'WarehousePanelProvider.php',
]);

test('filament shell normalizes titles in installed app windows', function (): void {
    expect(file_get_contents(resource_path('views/vendor/filament-panels/components/layout/base.blade.php')))
        ->toContain('(display-mode: standalone)')
        ->toContain('livewire:navigated')
        ->toContain('normalizeStandaloneTitle');
});

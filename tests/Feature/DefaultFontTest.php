<?php

test('the application default font is Plus Jakarta Sans', function () {
    expect(file_get_contents(resource_path('css/app.css')))
        ->toContain('Plus+Jakarta+Sans:wght@400;500;600;700')
        ->toContain("--font-sans: 'Plus Jakarta Sans'");

    expect(file_get_contents(resource_path('css/filament/admin/theme.css')))
        ->toContain('font-family: var(--font-family);');
});

test('filament panels use Plus Jakarta Sans', function (string $provider) {
    expect(file_get_contents(app_path("Providers/Filament/{$provider}")))
        ->toContain("->font('Plus Jakarta Sans')");
})->with([
    'AdminPanelProvider.php',
    'DesignPanelProvider.php',
    'FinancePanelProvider.php',
    'HrPanelProvider.php',
    'OperationsPanelProvider.php',
    'ProductionPanelProvider.php',
    'RetailPanelProvider.php',
    'SalesPanelProvider.php',
    'WarehousePanelProvider.php',
]);

<?php

test('the application default font is Albert Sans', function () {
    expect(file_get_contents(resource_path('css/app.css')))
        ->toContain('albert-sans:400,500,600,700')
        ->toContain("--font-sans: 'Albert Sans'");

    expect(file_get_contents(resource_path('css/filament/admin/theme.css')))
        ->toContain('font-family: var(--font-family);');
});

test('filament panels use Albert Sans', function (string $provider) {
    expect(file_get_contents(app_path("Providers/Filament/{$provider}")))
        ->toContain("->font('Albert Sans')");
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

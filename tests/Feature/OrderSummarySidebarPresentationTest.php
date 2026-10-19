<?php

it('uses placeholder summary rows on order form sidebars', function (string $path): void {
    $contents = file_get_contents(base_path($path));

    expect($contents)
        ->toContain("Placeholder::make('summary_subtotal')")
        ->toContain("Placeholder::make('summary_tax_amount')")
        ->toContain("Placeholder::make('summary_total')")
        ->toContain(($path === 'app/Filament/Resources/JobOrders/Schemas/JobOrderForm.php' ? 'TextInput' : 'Hidden')."::make('subtotal')")
        ->toContain("Hidden::make('tax_amount')")
        ->toContain("Hidden::make('total')")
        ->toContain('orderSummary')
        ->not->toContain("Repeater::make('jobOrderTasks')");
})->with([
    'job order form' => ['app/Filament/Resources/JobOrders/Schemas/JobOrderForm.php'],
    'proforma form' => ['app/Filament/Resources/Proformas/Schemas/ProformaForm.php'],
    'purchase order form' => ['app/Filament/Resources/PurchaseOrders/Schemas/PurchaseOrderForm.php'],
    'sales order form' => ['app/Filament/Resources/SalesOrders/Schemas/SalesOrderForm.php'],
]);

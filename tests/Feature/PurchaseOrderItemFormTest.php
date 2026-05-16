<?php

test('purchase order item form does not expose system managed receiving fields as inputs', function (): void {
    $form = file_get_contents(base_path('app/Filament/Resources/PurchaseOrderItems/Schemas/PurchaseOrderItemForm.php'));

    expect($form)
        ->toContain("Placeholder::make('received_quantity')")
        ->not->toContain("TextInput::make('received_quantity')")
        ->not->toContain("TextInput::make('status')");
});

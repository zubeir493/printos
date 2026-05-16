<?php

use App\Filament\Resources\SalesOrders\Schemas\SalesOrderForm;

test('sales order imports replace blank repeater rows and merge matching rows', function (): void {
    $rows = SalesOrderForm::mergeImportedRows(
        [
            [
                'inventory_item_id' => null,
                'quantity' => 1,
                'unit_price' => 0,
                'total' => 0,
            ],
            [
                'inventory_item_id' => 10,
                'quantity' => 2,
                'unit_price' => 50,
                'total' => 100,
            ],
        ],
        [
            [
                'inventory_item_id' => 20,
                'quantity' => 3,
                'unit_price' => 30,
                'total' => 90,
            ],
            [
                'inventory_item_id' => 10,
                'quantity' => 4,
                'unit_price' => 50,
                'total' => 200,
            ],
        ],
    );

    expect($rows)->toHaveCount(2);
    expect($rows[0]['inventory_item_id'])->toBe(10);
    expect($rows[0]['quantity'])->toBe(6.0);
    expect($rows[0]['total'])->toBe(300.0);
    expect($rows[1]['inventory_item_id'])->toBe(20);
    expect($rows[1]['quantity'])->toBe(3);
    expect($rows[1]['total'])->toBe(90);
});

test('sales order import modal links to the example csv template', function (): void {
    expect(file_get_contents(base_path('app/Filament/Resources/SalesOrders/Schemas/SalesOrderForm.php')))
        ->toContain('import-templates/sales-order-items.csv');

    expect(file_get_contents(public_path('import-templates/sales-order-items.csv')))
        ->toContain('sku,name,quantity,unit_price');
});

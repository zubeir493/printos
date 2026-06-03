<?php

use App\Filament\Resources\PurchaseOrders\Pages\CreatePurchaseOrder;
use App\Models\InventoryItem;
use App\Models\Partner;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use App\UserRole;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('purchase order form saves every item from the repeater', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('operations'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Operations,
    ]));

    $supplier = Partner::factory()->create(['is_supplier' => true]);
    $paper = InventoryItem::factory()->create([
        'name' => 'Paper',
        'sku' => 'PAPER-PO',
        'unit' => 'ream',
        'purchase_unit' => 'ream',
        'conversion_factor' => 1,
        'price' => 50,
        'average_cost' => 50,
    ]);
    $ink = InventoryItem::factory()->create([
        'name' => 'Ink',
        'sku' => 'INK-PO',
        'unit' => 'bottle',
        'purchase_unit' => 'bottle',
        'conversion_factor' => 1,
        'price' => 30,
        'average_cost' => 30,
    ]);

    Livewire::test(CreatePurchaseOrder::class)
        ->fillForm([
            'partner_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'purchaseOrderItems' => [
                [
                    'inventory_item_id' => $paper->id,
                    'quantity' => 2,
                    'unit_price' => 50,
                    'total' => 100,
                ],
                [
                    'inventory_item_id' => $ink->id,
                    'quantity' => 3,
                    'unit_price' => 30,
                    'total' => 90,
                ],
            ],
            'status' => 'draft',
            'due_date' => now()->addDays(30)->toDateString(),
            'subtotal' => 190,
            'tax_amount' => 0,
            'total' => 190,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('purchase_order_items', [
        'inventory_item_id' => $paper->id,
        'quantity' => 2,
        'unit_price' => 50,
        'total' => 100,
    ]);

    $this->assertDatabaseHas('purchase_order_items', [
        'inventory_item_id' => $ink->id,
        'quantity' => 3,
        'unit_price' => 30,
        'total' => 90,
    ]);

    expect(PurchaseOrderItem::count())->toBe(2);
});

test('custom repeater add actions use stable keys', function (string $path): void {
    expect(file_get_contents(base_path($path)))
        ->not->toContain('$state[] =')
        ->toContain('Str::uuid()');
})->with([
    'job order form' => ['app/Filament/Resources/JobOrders/Schemas/JobOrderForm.php'],
    'job order task form' => ['app/Filament/Resources/JobOrderTasks/Schemas/JobOrderTaskForm.php'],
    'production plan form' => ['app/Filament/Resources/ProductionPlans/Schemas/ProductionPlanForm.php'],
    'purchase order form' => ['app/Filament/Resources/PurchaseOrders/Schemas/PurchaseOrderForm.php'],
    'sales order form' => ['app/Filament/Resources/SalesOrders/Schemas/SalesOrderForm.php'],
]);

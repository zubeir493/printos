<?php

use App\Filament\Resources\Dispatches\Pages\CreateDispatch;
use App\Filament\Resources\Dispatches\Pages\EditDispatch;
use App\Filament\Resources\Dispatches\Pages\ListDispatches;
use App\Models\Dispatch;
use App\Models\DispatchItem;
use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\JobOrder;
use App\Models\Partner;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\UserRole;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('creates dispatch stock movement while lazy loading is prevented', function () {
    Filament::setCurrentPanel(Filament::getPanel('warehouse'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Warehouse,
    ]));

    $warehouse = Warehouse::factory()->create([
        'is_default' => true,
    ]);

    $jobOrder = JobOrder::factory()->create([
        'partner_id' => Partner::factory(),
        'production_mode' => 'make_to_order',
        'status' => 'design',
    ]);

    $task = tap($jobOrder, fn (JobOrder $record) => $record->updateQuietly([

        'name' => 'Binding',
        'quantity' => 50,
        'status' => 'production',
    ]));

    $wipItem = InventoryItem::factory()->create([
        'name' => 'Binding WIP',
        'sku' => 'JOB-'.$task->id,
        'unit' => 'pcs',
        'purchase_unit' => 'pcs',
        'conversion_factor' => 1,
        'type' => 'wip',
        'is_sellable' => false,
    ]);

    $task->updateQuietly(['output_inventory_item_id' => $wipItem->id]);
    StockMovement::withoutEvents(fn () => StockMovement::create([
        'inventory_item_id' => $wipItem->id, 'warehouse_id' => $warehouse->id,
        'type' => 'production_output', 'reference_type' => JobOrder::class, 'reference_id' => $task->id,
        'quantity' => $task->quantity, 'movement_date' => now(),
    ]));
    InventoryBalance::create([
        'inventory_item_id' => $wipItem->id,
        'warehouse_id' => $warehouse->id,
        'quantity_on_hand' => 50,
    ]);

    Model::preventLazyLoading();

    try {
        Livewire::test(CreateDispatch::class)
            ->fillForm([
                'job_order_id' => $jobOrder->id,
                'warehouse_id' => $warehouse->id,
                'delivery_date' => now()->toDateString(),
                'quantity' => 10,
            ])
            ->call('create')
            ->assertHasNoFormErrors();
    } finally {
        Model::preventLazyLoading(false);
    }

    $this->assertDatabaseHas('dispatch_items', [
        'job_order_id' => $task->id,
        'quantity' => 10,
    ]);

    $this->assertDatabaseHas('stock_movements', [
        'inventory_item_id' => $wipItem->id,
        'warehouse_id' => $warehouse->id,
        'type' => 'dispatch',
        'quantity' => -10,
    ]);

    $this->assertDatabaseHas('dispatches', [
        'job_order_id' => $jobOrder->id,
        'warehouse_id' => $warehouse->id,
        'status' => 'pending',
    ]);
});

it('halts dispatch creation with a notification when stock is insufficient', function () {
    Filament::setCurrentPanel(Filament::getPanel('warehouse'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Warehouse,
    ]));

    $warehouse = Warehouse::factory()->create([
        'is_default' => true,
    ]);

    $jobOrder = JobOrder::factory()->create([
        'partner_id' => Partner::factory(),
        'production_mode' => 'make_to_order',
        'status' => 'design',
    ]);

    $task = tap($jobOrder, fn (JobOrder $record) => $record->updateQuietly([

        'name' => 'Binding',
        'quantity' => 5000,
        'status' => 'production',
    ]));

    $wipItem = InventoryItem::factory()->create([
        'name' => 'Binding WIP',
        'sku' => 'JOB-'.$task->id,
        'unit' => 'pcs',
        'purchase_unit' => 'pcs',
        'conversion_factor' => 1,
        'type' => 'wip',
        'is_sellable' => false,
    ]);

    $task->updateQuietly(['output_inventory_item_id' => $wipItem->id]);
    StockMovement::withoutEvents(fn () => StockMovement::create([
        'inventory_item_id' => $wipItem->id, 'warehouse_id' => $warehouse->id,
        'type' => 'production_output', 'reference_type' => JobOrder::class, 'reference_id' => $task->id,
        'quantity' => $task->quantity, 'movement_date' => now(),
    ]));
    InventoryBalance::create([
        'inventory_item_id' => $wipItem->id,
        'warehouse_id' => $warehouse->id,
        'quantity_on_hand' => 1000,
    ]);

    Livewire::test(CreateDispatch::class)
        ->fillForm([
            'job_order_id' => $jobOrder->id,
            'warehouse_id' => $warehouse->id,
            'delivery_date' => now()->toDateString(),
            'quantity' => 5000,
        ])
        ->call('create')
        ->assertHasFormErrors(['quantity']);

    $this->assertDatabaseMissing('dispatches', [
        'job_order_id' => $jobOrder->id,
        'warehouse_id' => $warehouse->id,
    ]);

    $this->assertDatabaseMissing('dispatch_items', [
        'job_order_id' => $task->id,
        'quantity' => 5000,
    ]);

    $this->assertDatabaseMissing('stock_movements', [
        'inventory_item_id' => $wipItem->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => -5000,
    ]);
});

it('halts dispatch editing with a notification when the increase exceeds available stock', function () {
    Filament::setCurrentPanel(Filament::getPanel('warehouse'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Warehouse,
    ]));

    $warehouse = Warehouse::factory()->create([
        'is_default' => true,
    ]);

    $jobOrder = JobOrder::factory()->create([
        'partner_id' => Partner::factory(),
        'production_mode' => 'make_to_order',
        'status' => 'design',
    ]);

    $task = tap($jobOrder, fn (JobOrder $record) => $record->updateQuietly([

        'name' => 'Binding',
        'quantity' => 50,
        'status' => 'production',
    ]));

    $wipItem = InventoryItem::factory()->create([
        'name' => 'Binding WIP',
        'sku' => 'JOB-'.$task->id,
        'unit' => 'pcs',
        'purchase_unit' => 'pcs',
        'conversion_factor' => 1,
        'type' => 'wip',
        'is_sellable' => false,
    ]);

    $task->updateQuietly(['output_inventory_item_id' => $wipItem->id]);
    StockMovement::withoutEvents(fn () => StockMovement::create([
        'inventory_item_id' => $wipItem->id, 'warehouse_id' => $warehouse->id,
        'type' => 'production_output', 'reference_type' => JobOrder::class, 'reference_id' => $task->id,
        'quantity' => $task->quantity, 'movement_date' => now(),
    ]));
    InventoryBalance::create([
        'inventory_item_id' => $wipItem->id,
        'warehouse_id' => $warehouse->id,
        'quantity_on_hand' => 0,
    ]);

    $dispatch = Dispatch::factory()->create([
        'warehouse_id' => $warehouse->id,
        'job_order_id' => $jobOrder->id,
        'status' => 'pending',
    ]);

    DispatchItem::factory()->create([
        'dispatch_id' => $dispatch->id,
        'job_order_id' => $task->id,
        'quantity' => 10,
    ]);

    Livewire::test(EditDispatch::class, ['record' => $dispatch->id])
        ->fillForm([
            'job_order_id' => $jobOrder->id,
            'warehouse_id' => $warehouse->id,
            'delivery_date' => now()->toDateString(),
            'quantity' => 20,
        ])
        ->call('save')
        ->assertHasFormErrors(['quantity']);

    expect($dispatch->dispatchItems()->where('job_order_id', $task->id)->value('quantity'))->toBe(10);

    $this->assertDatabaseMissing('stock_movements', [
        'inventory_item_id' => $wipItem->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => -10,
    ]);
});

it('updates dispatch status from table actions', function (string $action, string $expectedStatus) {
    Filament::setCurrentPanel(Filament::getPanel('warehouse'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Warehouse,
    ]));

    $dispatch = Dispatch::factory()->create([
        'warehouse_id' => Warehouse::factory(),
        'job_order_id' => JobOrder::factory([
            'partner_id' => Partner::factory(),
            'status' => 'production',
        ]),
        'status' => 'pending',
    ]);

    Livewire::test(ListDispatches::class)
        ->callTableAction($action, $dispatch)
        ->assertHasNoTableActionErrors();

    expect($dispatch->fresh()->status)->toBe($expectedStatus);
})->with([
    'mark as delivered' => ['complete_dispatch', 'completed'],
    'cancel' => ['cancel_dispatch', 'cancelled'],
]);

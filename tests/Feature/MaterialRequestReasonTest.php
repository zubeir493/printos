<?php

use App\Filament\Resources\JobOrderTasks\Pages\ListJobOrderTasks;
use App\Filament\Resources\JobOrderTasks\Pages\ViewJobOrderTask;
use App\Filament\Resources\MaterialRequests\Pages\ManageMaterialRequests;
use App\Models\InventoryItem;
use App\Models\JobOrder;
use App\Models\JobOrderTask;
use App\Models\MaterialRequest;
use App\Models\Partner;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\UserRole;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('stores the batch reason when requesting materials from the task table', function () {
    Filament::setCurrentPanel(Filament::getPanel('production'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Production,
    ]));

    [$task, $item] = makeProductionTaskWithPaper();

    Livewire::test(ListJobOrderTasks::class)
        ->callTableAction('request_materials', $task, [
            'items' => [[
                'inventory_item_id' => $item->id,
                'requested_quantity' => 25,
                'paper_index' => 0,
            ]],
            'reason' => 'Needed for urgent reprint',
        ])
        ->assertHasNoTableActionErrors();

    $this->assertDatabaseHas('material_requests', [
        'job_order_task_id' => $task->id,
        'inventory_item_id' => $item->id,
        'requested_quantity' => 25,
        'reason' => 'Needed for urgent reprint',
    ]);
});

it('stores the batch reason when requesting materials from the task view page', function () {
    Filament::setCurrentPanel(Filament::getPanel('production'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Production,
    ]));

    [$task, $item] = makeProductionTaskWithPaper();

    Livewire::test(ViewJobOrderTask::class, ['record' => $task->id])
        ->callAction('request_materials', [
            'items' => [[
                'inventory_item_id' => $item->id,
                'requested_quantity' => 25,
                'paper_index' => 0,
            ]],
            'reason' => 'Needed for urgent reprint',
        ])
        ->assertHasNoActionErrors();

    $this->assertDatabaseHas('material_requests', [
        'job_order_task_id' => $task->id,
        'inventory_item_id' => $item->id,
        'requested_quantity' => 25,
        'reason' => 'Needed for urgent reprint',
    ]);
});

it('opens the material request create action and stores the reason', function () {
    Filament::setCurrentPanel(Filament::getPanel('production'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Production,
    ]));

    [$task, $item] = makeProductionTaskWithPaper();

    Livewire::test(ManageMaterialRequests::class)
        ->mountAction('create')
        ->setActionData([
            'job_order_task_id' => $task->id,
            'inventory_item_id' => $item->id,
            'required_quantity' => 25,
            'requested_quantity' => 25,
            'issued_quantity' => 0,
            'reason' => 'Needed from direct material request',
        ])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    $this->assertDatabaseHas('material_requests', [
        'job_order_task_id' => $task->id,
        'inventory_item_id' => $item->id,
        'requested_quantity' => 25,
        'reason' => 'Needed from direct material request',
    ]);
});

it('defaults table production logging quantity to the remaining unlogged quantity', function () {
    Filament::setCurrentPanel(Filament::getPanel('production'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Production,
    ]));

    [$task] = makeTaskReadyForProductionLog();

    Livewire::test(ListJobOrderTasks::class)
        ->mountTableAction('log_production', $task)
        ->assertTableActionDataSet([
            'quantity' => 60.0,
        ]);
});

it('defaults view page production logging quantity to the remaining unlogged quantity', function () {
    Filament::setCurrentPanel(Filament::getPanel('production'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Production,
    ]));

    [$task] = makeTaskReadyForProductionLog();

    Livewire::test(ViewJobOrderTask::class, ['record' => $task->id])
        ->mountAction('log_production')
        ->assertActionDataSet([
            'quantity' => 60.0,
        ]);
});

function makeProductionTaskWithPaper(): array
{
    $item = InventoryItem::factory()->create([
        'name' => 'Art Card',
        'sku' => 'ART-CARD',
        'unit' => 'sheet',
        'purchase_unit' => 'sheet',
        'conversion_factor' => 1,
        'type' => 'raw_material',
        'is_sellable' => false,
        'price' => 12,
    ]);

    $jobOrder = JobOrder::factory()->create([
        'partner_id' => Partner::factory(),
        'status' => 'active',
        'production_mode' => 'make_to_order',
    ]);

    $task = JobOrderTask::factory()->create([
        'job_order_id' => $jobOrder->id,
        'name' => 'Printing',
        'quantity' => 100,
        'status' => 'production',
        'paper' => [[
            'inventory_item_id' => $item->id,
            'required_quantity' => 25,
        ]],
    ]);

    return [$task, $item];
}

function makeTaskReadyForProductionLog(): array
{
    [$task, $item] = makeProductionTaskWithPaper();

    $warehouse = Warehouse::factory()->create([
        'is_default' => true,
    ]);

    MaterialRequest::create([
        'job_order_task_id' => $task->id,
        'inventory_item_id' => $item->id,
        'required_quantity' => 100,
        'requested_quantity' => 100,
        'issued_quantity' => 100,
        'reason' => 'Materials issued for production',
    ]);

    StockMovement::factory()->create([
        'inventory_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'type' => 'production_output',
        'reference_type' => JobOrderTask::class,
        'reference_id' => $task->id,
        'quantity' => 40,
        'movement_date' => now(),
    ]);

    return [$task, $item, $warehouse];
}

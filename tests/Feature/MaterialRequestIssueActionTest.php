<?php

use App\Filament\Resources\JobOrderTasks\Pages\ListJobOrderTasks;
use App\Filament\Resources\MaterialRequests\Pages\ManageMaterialRequests;
use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\JobOrder;
use App\Models\JobOrderTask;
use App\Models\MaterialRequest;
use App\Models\Partner;
use App\Models\User;
use App\Models\Warehouse;
use App\UserRole;
use Filament\Actions\Action;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('shows the material name in the issue modal heading', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('warehouse'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Warehouse,
    ]));

    [$materialRequest] = makeMaterialIssueScenario(stock: 10);

    Livewire::test(ManageMaterialRequests::class)
        ->assertActionExists(
            TestAction::make('issue')->table($materialRequest),
            fn (Action $action): bool => $action->getModalHeading() === 'Issue Art Card',
        );
});

it('renders the request date on the material requests table', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('warehouse'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Warehouse,
    ]));

    makeMaterialIssueScenario(stock: 10);

    Livewire::test(ManageMaterialRequests::class)
        ->assertTableColumnExists('created_at')
        ->assertCanRenderTableColumn('created_at');
});

it('shows a validation error when issuing more than available stock', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('warehouse'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Warehouse,
    ]));

    [$materialRequest, $warehouse] = makeMaterialIssueScenario(stock: 10);

    Livewire::test(ManageMaterialRequests::class)
        ->callAction(TestAction::make('issue')->table($materialRequest), data: [
            'warehouse_id' => $warehouse->id,
            'quantity' => 15,
        ])
        ->assertHasFormErrors(['quantity']);

    expect($materialRequest->refresh()->issued_quantity)->toBe('0.00');
});

it('uses compact material action modals and shows units while requesting materials', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('production'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Production,
    ]));

    [, , , $task] = makeMaterialIssueScenario(stock: 10);

    Livewire::test(ListJobOrderTasks::class)
        ->assertActionExists(
            TestAction::make('request_materials')->table($task),
            fn (Action $action): bool => $action->getModalWidth() === 'lg',
        )
        ->mountAction(TestAction::make('request_materials')->table($task))
        ->assertMountedActionModalSee('sheet');
});

it('shows a validation error when the issue materials popup exceeds available stock', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Warehouse,
    ]));

    [$materialRequest, $warehouse, $item, $task] = makeMaterialIssueScenario(stock: 10);

    Livewire::test(ListJobOrderTasks::class)
        ->assertActionExists(
            TestAction::make('issue_materials')->table($task),
            fn (Action $action): bool => $action->getModalWidth() === 'lg',
        )
        ->callAction(TestAction::make('issue_materials')->table($task), data: [
            'warehouse_id' => $warehouse->id,
            'items' => [[
                'material_request_id' => $materialRequest->id,
                'inventory_item_id' => $item->id,
                'quantity' => 15,
            ]],
        ])
        ->assertHasFormErrors(['items.0.quantity']);

    expect($materialRequest->refresh()->issued_quantity)->toBe('0.00');
});

it('validates invalid request material rows instead of throwing', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('production'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Production,
    ]));

    [, , , $task] = makeMaterialIssueScenario(stock: 10);

    Livewire::test(ListJobOrderTasks::class)
        ->callAction(TestAction::make('request_materials')->table($task), data: [
            'items' => [[
                'inventory_item_id' => 999999,
                'requested_quantity' => 5,
                'paper_index' => 0,
            ]],
            'reason' => 'Needed for production',
        ])
        ->assertHasFormErrors(['items.0.inventory_item_id']);
});

function makeMaterialIssueScenario(int|float $stock): array
{
    $warehouse = Warehouse::factory()->create([
        'is_default' => true,
    ]);

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

    InventoryBalance::factory()->create([
        'inventory_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity_on_hand' => $stock,
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

    $materialRequest = MaterialRequest::create([
        'job_order_task_id' => $task->id,
        'inventory_item_id' => $item->id,
        'required_quantity' => 25,
        'requested_quantity' => 25,
        'issued_quantity' => 0,
        'reason' => 'Needed for production',
    ]);

    return [$materialRequest, $warehouse, $item, $task];
}

<?php

use App\Filament\Resources\MaintenanceStoreEntries\MaintenanceStoreEntryResource;
use App\Filament\Resources\MaintenanceStoreEntries\Pages\ManageMaintenanceStoreEntries;
use App\Models\Employee;
use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\Machine;
use App\Models\MaintenanceStoreEntry;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\MaintenanceStore;
use App\UserRole;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('checks out a tool and records partial and complete returns from the maintenance store', function (): void {
    $warehouse = Warehouse::factory()->create();
    $staff = User::factory()->create(['role' => UserRole::Warehouse]);
    $staff->warehouses()->attach($warehouse);
    $this->actingAs($staff);
    Filament::setCurrentPanel(Filament::getPanel('warehouse'));

    $tool = InventoryItem::factory()->create(['type' => 'tools', 'unit' => 'pcs', 'average_cost' => 0]);
    $balance = InventoryBalance::factory()->create([
        'inventory_item_id' => $tool->id,
        'warehouse_id' => $warehouse->id,
        'quantity_on_hand' => 5,
    ]);
    $employee = Employee::factory()->create(['status' => 'active']);

    $this->get(MaintenanceStoreEntryResource::getUrl(panel: 'warehouse'))
        ->assertSuccessful()->assertSee('Maintenance store');

    $page = Livewire::test(ManageMaintenanceStoreEntries::class)
        ->mountAction('checkoutTool')
        ->fillForm([
            'warehouse_id' => $warehouse->id,
            'inventory_item_id' => $tool->id,
            'employee_id' => $employee->id,
            'quantity' => 3,
        ])
        ->assertFormFieldExists('quantity', fn (TextInput $field): bool => str_contains(
            (string) $field->getChildSchema(TextInput::BELOW_CONTENT_SCHEMA_KEY)?->toHtml(),
            'Available in this warehouse · 5 pcs',
        ))
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertNotified('Tool checked out');

    $checkout = MaintenanceStoreEntry::query()->sole();
    $return = TestAction::make('returnTool')->table($checkout);
    $page->assertCanSeeTableRecords([$checkout])->assertSee($employee->full_name)
        ->callAction($return, data: ['quantity' => 1])
        ->assertHasNoActionErrors()->assertNotified('Tool return recorded');

    expect($checkout->fresh()->outstandingQuantity())->toBe(2.0)
        ->and($balance->fresh()->quantity_on_hand)->toBe('3.00');

    $page->callAction($return, data: ['quantity' => 2])
        ->assertHasNoActionErrors()
        ->assertActionHidden($return);

    expect($checkout->fresh()->outstandingQuantity())->toBe(0.0)
        ->and($balance->fresh()->quantity_on_hand)->toBe('5.00');
});

it('shows a spare-part stock error in the action and allows staff to correct the quantity', function (): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $warehouse = Warehouse::factory()->create();
    $part = InventoryItem::factory()->create(['type' => 'spare_parts', 'average_cost' => 0]);
    $machine = Machine::create(['name' => 'Press under repair', 'code' => 'REPAIR-PRESS', 'is_active' => false]);
    $balance = InventoryBalance::factory()->create([
        'inventory_item_id' => $part->id,
        'warehouse_id' => $warehouse->id,
        'quantity_on_hand' => 3,
    ]);

    $page = Livewire::test(ManageMaintenanceStoreEntries::class)
        ->mountAction('issueSparePart')
        ->fillForm([
            'warehouse_id' => $warehouse->id,
            'inventory_item_id' => $part->id,
            'machine_id' => $machine->id,
            'quantity' => 4,
            'reference' => 'Repair WO-118',
        ])
        ->callMountedAction()
        ->assertHasActionErrors(['quantity']);

    expect(MaintenanceStoreEntry::count())->toBe(0)
        ->and($balance->fresh()->quantity_on_hand)->toBe('3.00');

    $page->fillForm(['quantity' => 1.25])
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertNotified('Spare parts issued');

    $issue = MaintenanceStoreEntry::query()->sole();
    $page->assertActionHidden(TestAction::make('returnTool')->table($issue));
    expect($issue->machine_id)->toBe($machine->id)
        ->and($issue->reference)->toBe('Repair WO-118')
        ->and($balance->fresh()->quantity_on_hand)->toBe('1.75');
});

it('only lists stock transactions for warehouses assigned to the signed-in staff member', function (): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $assignedWarehouse = Warehouse::factory()->create();
    $otherWarehouse = Warehouse::factory()->create();
    $tool = InventoryItem::factory()->create(['type' => 'tools', 'average_cost' => 0]);
    $employee = Employee::factory()->create(['status' => 'active']);
    foreach ([$assignedWarehouse, $otherWarehouse] as $warehouse) {
        InventoryBalance::factory()->create([
            'inventory_item_id' => $tool->id,
            'warehouse_id' => $warehouse->id,
            'quantity_on_hand' => 5,
        ]);
    }
    $service = app(MaintenanceStore::class);
    $assignedCheckout = $service->checkoutTool($tool->id, $assignedWarehouse->id, $employee->id, 1);
    $outsideCheckout = $service->checkoutTool($tool->id, $otherWarehouse->id, $employee->id, 1);

    $staff = User::factory()->create(['role' => UserRole::Warehouse]);
    $staff->warehouses()->attach($assignedWarehouse);
    $this->actingAs($staff);
    Filament::setCurrentPanel(Filament::getPanel('warehouse'));

    Livewire::test(ManageMaintenanceStoreEntries::class)
        ->assertCanSeeTableRecords([$assignedCheckout])
        ->assertCanNotSeeTableRecords([$outsideCheckout])
        ->callAction('checkoutTool', data: [
            'warehouse_id' => $otherWarehouse->id,
            'inventory_item_id' => $tool->id,
            'employee_id' => $employee->id,
            'quantity' => 1,
        ])
        ->assertHasActionErrors(['warehouse_id']);
});

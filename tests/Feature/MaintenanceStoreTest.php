<?php

use App\Models\Employee;
use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\Machine;
use App\Models\MaintenanceStoreEntry;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\MaintenanceStore;
use App\UserRole;
use Database\Seeders\MaintenanceStoreEntrySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actor = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($this->actor);
});

it('tracks tool custody and posts each partial return to the original warehouse', function (): void {
    $balance = maintenanceStock('tools');
    $employee = Employee::factory()->create();
    $service = app(MaintenanceStore::class);
    $checkout = $service->checkoutTool($balance->inventory_item_id, $balance->warehouse_id, $employee->id, 4, today()->addWeek()->toDateString(), 'Press setup');

    expect($checkout->outstandingQuantity())->toBe(4.0)
        ->and($checkout->recorded_by)->toBe($this->actor->id)
        ->and((float) $balance->fresh()->quantity_on_hand)->toBe(6.0);

    $employee->delete();
    $checkout = $service->returnTool($checkout, 1);
    expect($checkout->outstandingQuantity())->toBe(3.0)
        ->and($checkout->employee->id)->toBe($employee->id);
    expect(fn () => $service->returnTool($checkout, 4))->toThrow(ValidationException::class);

    $checkout = $service->returnTool($checkout, 3);
    expect($checkout->outstandingQuantity())->toBe(0.0)
        ->and((float) $balance->fresh()->quantity_on_hand)->toBe(10.0)
        ->and($checkout->stockMovements()->orderBy('id')->pluck('type')->all())->toBe(['tool_checkout', 'tool_return', 'tool_return'])
        ->and($checkout->stockMovements()->orderBy('id')->pluck('quantity')->map(fn ($quantity): float => (float) $quantity)->all())->toBe([-4.0, 1.0, 3.0]);
    expect(fn () => $service->returnTool($checkout, 1))->toThrow(ValidationException::class);
});

it('issues spare parts to a machine in base units and rejects incompatible or excessive quantities', function (): void {
    $balance = maintenanceStock('spare_parts');
    $tool = maintenanceStock('tools');
    $machine = Machine::create(['name' => 'Offset press', 'code' => 'MAINT-PRESS', 'operation_type' => 'printing', 'is_active' => false]);
    $service = app(MaintenanceStore::class);

    expect(fn () => $service->issueSparePart($tool->inventory_item_id, $tool->warehouse_id, $machine->id, 1))->toThrow(ValidationException::class)
        ->and(fn () => $service->issueSparePart($balance->inventory_item_id, $balance->warehouse_id, $machine->id, 11))->toThrow(ValidationException::class)
        ->and(fn () => $service->issueSparePart($balance->inventory_item_id, $balance->warehouse_id, $machine->id, 1.125))->toThrow(ValidationException::class);

    $issue = $service->issueSparePart($balance->inventory_item_id, $balance->warehouse_id, $machine->id, 1.25, 'REPAIR-17');
    expect($issue->reference)->toBe('REPAIR-17')
        ->and($issue->machine->is($machine))->toBeTrue()
        ->and((float) $balance->fresh()->quantity_on_hand)->toBe(8.75)
        ->and((float) $issue->stockMovements()->sole()->quantity)->toBe(-1.25)
        ->and(MaintenanceStoreEntry::count())->toBe(1)
        ->and(fn () => $service->returnTool($issue, 1))->toThrow(ValidationException::class);
});

it('enforces warehouse assignments and maintenance roles at the transaction boundary', function (): void {
    $balance = maintenanceStock('tools');
    $employee = Employee::factory()->create();
    $service = app(MaintenanceStore::class);
    $checkout = $service->checkoutTool($balance->inventory_item_id, $balance->warehouse_id, $employee->id, 1);
    $staff = User::factory()->create(['role' => UserRole::Warehouse]);
    $this->actingAs($staff);

    expect(fn () => $service->checkoutTool($balance->inventory_item_id, $balance->warehouse_id, $employee->id, 1))->toThrow(HttpException::class, 'not assigned')
        ->and(fn () => $service->returnTool($checkout, 1))->toThrow(HttpException::class, 'not assigned');

    $staff->warehouses()->attach($balance->warehouse_id);
    expect($service->returnTool($checkout, 1)->outstandingQuantity())->toBe(0.0);

    $this->actingAs(User::factory()->create(['role' => UserRole::Finance]));
    expect(fn () => $service->checkoutTool($balance->inventory_item_id, $balance->warehouse_id, $employee->id, 1))->toThrow(HttpException::class);
});

it('rolls back the register entry if posting the stock movement fails', function (): void {
    $balance = maintenanceStock('tools');
    $employee = Employee::factory()->create();
    $this->mock(InventoryService::class)->shouldReceive('createMovement')->once()->andThrow(new RuntimeException('Movement write failed'));

    expect(fn () => app(MaintenanceStore::class)->checkoutTool($balance->inventory_item_id, $balance->warehouse_id, $employee->id, 2))
        ->toThrow(RuntimeException::class, 'Movement write failed');

    expect(MaintenanceStoreEntry::count())->toBe(0)
        ->and((float) $balance->fresh()->quantity_on_hand)->toBe(10.0);
});

it('seeds a repeatable local sample through the stock workflow', function (): void {
    $this->seed(MaintenanceStoreEntrySeeder::class);
    $this->seed(MaintenanceStoreEntrySeeder::class);

    expect(MaintenanceStoreEntry::withCount('stockMovements')->pluck('stock_movements_count')->all())->toBe([1, 1])
        ->and((float) InventoryBalance::whereIn('inventory_item_id', InventoryItem::where('sku', 'like', 'DEMO-MAINT-%')->select('id'))->sum('quantity_on_hand'))->toBe(7.0);
});

function maintenanceStock(string $type): InventoryBalance
{
    return InventoryBalance::factory()->create([
        'inventory_item_id' => InventoryItem::factory()->create(['type' => $type, 'unit' => 'piece', 'average_cost' => 0])->id,
        'warehouse_id' => Warehouse::factory()->create()->id,
        'quantity_on_hand' => 10,
    ]);
}

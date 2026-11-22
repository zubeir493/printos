<?php

use App\Filament\Pages\StockOverview;
use App\Filament\Resources\Dispatches\DispatchResource;
use App\Filament\Resources\GoodsReceipts\GoodsReceiptResource;
use App\Filament\Resources\StockAdjustments\Pages\CreateStockAdjustment;
use App\Filament\Resources\StockAdjustments\StockAdjustmentResource;
use App\Filament\Resources\StockMovements\Pages\ListStockMovements;
use App\Filament\Resources\StockMovements\StockMovementResource;
use App\Filament\Resources\StockTransfers\StockTransferResource;
use App\Filament\Resources\Warehouses\WarehouseResource;
use App\Models\Dispatch;
use App\Models\GoodsReceipt;
use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\JobOrder;
use App\Models\MaterialRequest;
use App\Models\PurchaseOrder;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\MaterialIssueService;
use App\Services\ReturnJobMaterials;
use App\UserRole;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

it('limits warehouse resource lists, direct records, and search by role and assignment', function (): void {
    $assigned = Warehouse::factory()->create();
    $other = Warehouse::factory()->create();
    $purchase = PurchaseOrder::factory()->create();
    $movement = StockMovement::factory()->create(['warehouse_id' => $assigned->id, 'quantity' => 10]);
    $otherMovement = StockMovement::factory()->create(['warehouse_id' => $other->id, 'quantity' => 10]);
    $dispatch = Dispatch::factory()->create(['warehouse_id' => $assigned->id]);
    $otherDispatch = Dispatch::factory()->create(['warehouse_id' => $other->id]);
    $receipt = GoodsReceipt::create([
        'purchase_order_id' => $purchase->id, 'warehouse_id' => $assigned->id, 'receipt_date' => today(), 'status' => 'draft',
    ]);
    GoodsReceipt::create([
        'purchase_order_id' => $purchase->id, 'warehouse_id' => $other->id, 'receipt_date' => today(), 'status' => 'draft',
    ]);
    $staff = User::factory()->create(['role' => UserRole::Warehouse]);
    $staff->warehouses()->attach($assigned);
    $this->actingAs($staff);
    Filament::setCurrentPanel(Filament::getPanel('warehouse'));

    Livewire::test(ListStockMovements::class)
        ->assertCanSeeTableRecords([$movement])
        ->assertCanNotSeeTableRecords([$otherMovement]);
    expect(WarehouseResource::getEloquentQuery()->pluck('id')->all())->toBe([$assigned->id])
        ->and(DispatchResource::getGlobalSearchEloquentQuery()->pluck('id')->all())->toBe([$dispatch->id])
        ->and(GoodsReceiptResource::getEloquentQuery()->pluck('id')->all())->toBe([$receipt->id]);
    $this->get(StockMovementResource::getUrl('view', ['record' => $movement], panel: 'warehouse'))->assertOk();
    $this->get(StockMovementResource::getUrl('view', ['record' => $otherMovement], panel: 'warehouse'))->assertRedirect('/warehouse');
    $this->get(DispatchResource::getUrl('view', ['record' => $otherDispatch], panel: 'warehouse'))->assertRedirect('/warehouse');
    expect(fn () => $otherDispatch->update(['status' => 'completed']))->toThrow(HttpException::class, 'not assigned')
        ->and(fn () => $receipt->update(['warehouse_id' => $other->id]))->toThrow(HttpException::class, 'not assigned');

    $this->actingAs(User::factory()->create(['role' => UserRole::Warehouse]));
    expect(WarehouseResource::getEloquentQuery()->count())->toBe(0)
        ->and(StockMovementResource::getEloquentQuery()->count())->toBe(0);

    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    expect(WarehouseResource::getEloquentQuery()->count())->toBe(2)
        ->and(StockMovementResource::getEloquentQuery()->count())->toBe(2);
});

it('limits stock overview even when selected warehouse state is outside the assignment', function (): void {
    $assigned = Warehouse::factory()->create();
    $other = Warehouse::factory()->create(['is_default' => true]);
    $allowedBalance = InventoryBalance::factory()->create(['warehouse_id' => $assigned->id, 'quantity_on_hand' => 8]);
    $otherBalance = InventoryBalance::factory()->create(['warehouse_id' => $other->id, 'quantity_on_hand' => 9]);
    $staff = User::factory()->create(['role' => UserRole::Warehouse]);
    $staff->warehouses()->attach($assigned);
    $this->actingAs($staff);
    Filament::setCurrentPanel(Filament::getPanel('warehouse'));

    Livewire::test(StockOverview::class)
        ->assertSet('warehouse_id', $assigned->id)
        ->assertCanSeeTableRecords([$allowedBalance])
        ->assertCanNotSeeTableRecords([$otherBalance])
        ->set('warehouse_id', $other->id)
        ->assertCanNotSeeTableRecords([$allowedBalance, $otherBalance]);
});

it('enforces warehouse assignments for adjustment and transfer writes and forms', function (): void {
    $assigned = Warehouse::factory()->create();
    $secondAssigned = Warehouse::factory()->create();
    $other = Warehouse::factory()->create();
    $adjustment = StockAdjustment::create(['warehouse_id' => $assigned->id, 'adjustment_date' => today(), 'status' => 'draft']);
    $otherAdjustment = StockAdjustment::create(['warehouse_id' => $other->id, 'adjustment_date' => today(), 'status' => 'draft']);
    $transfer = StockTransfer::factory()->create(['from_warehouse_id' => $assigned->id, 'to_warehouse_id' => $secondAssigned->id, 'status' => 'draft']);
    $outsideTransfer = StockTransfer::factory()->create(['from_warehouse_id' => $assigned->id, 'to_warehouse_id' => $other->id, 'status' => 'draft']);
    $staff = User::factory()->create(['role' => UserRole::Warehouse]);
    $staff->warehouses()->attach([$assigned->id, $secondAssigned->id]);
    $this->actingAs($staff);
    Filament::setCurrentPanel(Filament::getPanel('warehouse'));

    expect(StockAdjustmentResource::getEloquentQuery()->pluck('id')->all())->toBe([$adjustment->id])
        ->and(StockTransferResource::getEloquentQuery()->pluck('id')->all())->toBe([$transfer->id]);
    $this->get(StockTransferResource::getUrl('edit', ['record' => $outsideTransfer], panel: 'warehouse'))->assertRedirect('/warehouse');
    Livewire::test(CreateStockAdjustment::class)
        ->assertFormFieldExists('warehouse_id', checkFieldUsing: fn ($field): bool => collect($field->getOptions())->keys()->doesntContain($other->id));

    expect(fn () => $adjustment->update(['warehouse_id' => $other->id]))->toThrow(HttpException::class, 'not assigned')
        ->and(fn () => $otherAdjustment->delete())->toThrow(HttpException::class, 'not assigned')
        ->and(fn () => $outsideTransfer->post())->toThrow(HttpException::class, 'not assigned');
    expect($adjustment->fresh()->warehouse_id)->toBe($assigned->id);
});

it('prevents issue and return of job materials at unassigned warehouses', function (): void {
    $assigned = Warehouse::factory()->create();
    $other = Warehouse::factory()->create();
    $job = JobOrder::factory()->create(['status' => 'production']);
    $item = InventoryItem::factory()->create(['type' => 'raw_material']);
    foreach ([$assigned, $other] as $warehouse) {
        InventoryBalance::factory()->create(['warehouse_id' => $warehouse->id, 'inventory_item_id' => $item->id, 'quantity_on_hand' => 10]);
    }
    $request = MaterialRequest::create([
        'job_order_id' => $job->id, 'inventory_item_id' => $item->id, 'requested_quantity' => 10, 'required_quantity' => 10,
    ]);
    $issuer = app(MaterialIssueService::class);
    $issuer->issue($request, $other->id, 3);
    $staff = User::factory()->create(['role' => UserRole::Warehouse]);
    $staff->warehouses()->attach($assigned);
    $this->actingAs($staff);

    expect(fn () => $issuer->issue($request, $other->id, 1, $staff))->toThrow(HttpException::class, 'not assigned');
    $issuer->issue($request, $assigned->id, 2, $staff);
    $returns = app(ReturnJobMaterials::class);
    expect(array_column($returns->rowsFor($job), 'warehouse_id'))->toBe([$assigned->id])
        ->and(fn () => $returns->handle($job, [['material_request_id' => $request->id, 'warehouse_id' => $other->id, 'quantity' => 1]]))
        ->toThrow(HttpException::class, 'not assigned');
});

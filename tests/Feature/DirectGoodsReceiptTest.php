<?php

use App\Filament\Resources\GoodsReceipts\Pages\ListGoodsReceipts;
use App\Models\GoodsReceipt;
use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\Partner;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PostDirectGoodsReceipt;
use App\UserRole;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Notification::fake();
});

/** @return array<string, mixed> */
function receiptInputs(Warehouse $warehouse, InventoryItem $item): array
{
    return [
        'source' => 'opening_stock',
        'source_reference' => 'Opening count 001',
        'warehouse_id' => $warehouse->id,
        'receipt_date' => today()->subDay()->toDateString(),
        'items' => [['inventory_item_id' => $item->id, 'quantity_received' => 10]],
    ];
}

it('posts a supplier delivery from the receipt list in inventory units and retains its source', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('warehouse'));
    $warehouse = Warehouse::factory()->create();
    $user = User::factory()->create(['role' => UserRole::Warehouse]);
    $user->warehouses()->attach($warehouse);
    $this->actingAs($user);
    $supplier = Partner::factory()->create(['is_supplier' => true]);
    $plate = InventoryItem::factory()->create(['type' => 'raw_material', 'category' => 'plate', 'unit' => 'piece', 'purchase_unit' => 'box', 'conversion_factor' => 50, 'average_cost' => 10]);
    InventoryBalance::create(['inventory_item_id' => $plate->id, 'warehouse_id' => $warehouse->id, 'quantity_on_hand' => 10]);
    $data = [...receiptInputs($warehouse, $plate), 'source' => 'supplier', 'supplier_id' => $supplier->id, 'source_reference' => 'DN-21'];

    $page = Livewire::test(ListGoodsReceipts::class)
        ->callAction('directReceipt', data: $data)
        ->assertHasNoActionErrors()
        ->assertNotified('Receipt posted');

    $receipt = GoodsReceipt::query()->sole();
    $movement = StockMovement::query()->sole();

    expect($receipt->status)->toBe('posted')
        ->and($receipt->posted_at)->not->toBeNull()
        ->and($receipt->purchase_order_id)->toBeNull()
        ->and($receipt->supplier->is($supplier))->toBeTrue()
        ->and($receipt->source_reference)->toBe('DN-21')
        ->and($movement->type)->toBe(GoodsReceipt::DIRECT_MOVEMENT)
        ->and($movement->reference_type)->toBe(GoodsReceipt::class)
        ->and($movement->reference_id)->toBe($receipt->id)
        ->and($movement->movement_date->toDateString())->toBe($data['receipt_date'])
        ->and((float) $movement->quantity)->toBe(10.0)
        ->and($movement->unit_cost)->toBeNull()
        ->and($receipt->items()->sole()->unit_cost)->toBeNull()
        ->and((float) $plate->refresh()->average_cost)->toBe(10.0)
        ->and((float) InventoryBalance::sole()->quantity_on_hand)->toBe(20.0)
        ->and(PurchaseOrder::count())->toBe(0);

    $view = TestAction::make('viewReceipt')->table($receipt);
    $page->mountAction($view)->assertActionMounted($view);
    expect($page->effects['partials']['action-modals'] ?? $page->html())->toContain(e($plate->name));
});

it('records opening stock for a spare part without a supplier and guards repeated posting', function (): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $warehouse = Warehouse::factory()->create();
    $part = InventoryItem::factory()->create(['type' => 'spare_parts', 'average_cost' => 0]);
    $receipt = app(PostDirectGoodsReceipt::class)->handle(receiptInputs($warehouse, $part));

    $receipt->update(['status' => 'draft']);
    $receipt->update(['status' => 'posted']);

    expect($receipt->supplier_id)->toBeNull()
        ->and($receipt->sourceLabel())->toBe('Opening stock')
        ->and(StockMovement::count())->toBe(1)
        ->and((float) $part->refresh()->average_cost)->toBe(0.0);
});

it('rejects an unassigned warehouse and invalid lines without receiving stock', function (): void {
    $assigned = Warehouse::factory()->create();
    $other = Warehouse::factory()->create();
    $user = User::factory()->create(['role' => UserRole::Warehouse]);
    $user->warehouses()->attach($assigned);
    $this->actingAs($user);
    $item = InventoryItem::factory()->create();
    $service = app(PostDirectGoodsReceipt::class);

    expect(fn () => $service->handle(receiptInputs($other, $item)))->toThrow(HttpException::class, 'You are not assigned to this warehouse.');

    $data = receiptInputs($assigned, $item);
    $data['items'][] = ['inventory_item_id' => InventoryItem::factory()->create()->id, 'quantity_received' => -1];

    expect(fn () => $service->handle($data))->toThrow(ValidationException::class)
        ->and(fn () => $service->handle(array_replace_recursive(receiptInputs($assigned, $item), ['items' => [['unit_cost' => 20]]])))->toThrow(ValidationException::class)
        ->and(GoodsReceipt::count())->toBe(0)
        ->and(StockMovement::count())->toBe(0);
});

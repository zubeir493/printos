<?php

use App\Filament\Resources\StockMovements\Pages\ListStockMovements;
use App\Filament\Resources\StockMovements\Pages\ViewStockMovement;
use App\Filament\Resources\StockMovements\Schemas\StockMovementForm;
use App\Filament\Resources\StockMovements\StockMovementResource;
use App\Models\InventoryItem;
use App\Models\JobOrder;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\DateTimeDisplay;
use App\UserRole;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('uses a datetime picker for movement date so time is preserved', function () {
    $schema = StockMovementForm::configure(Schema::make());
    $components = (new ReflectionClass($schema))
        ->getProperty('components')
        ->getValue($schema);

    $movementDate = collect($components)
        ->first(fn ($component) => $component->getName() === 'movement_date');

    expect($movementDate)
        ->toBeInstanceOf(DateTimePicker::class);
});

it('shows stock movement timestamps in filament screens', function (string $path): void {
    expect(file_get_contents(base_path($path)))
        ->toContain(DateTimeDisplay::class);
})->with([
    'stock movement table' => 'app/Filament/Resources/StockMovements/Tables/StockMovementsTable.php',
    'stock movement infolist' => 'app/Filament/Resources/StockMovements/Schemas/StockMovementInfolist.php',
    'warehouse stock movements relation manager' => 'app/Filament/Resources/Warehouses/RelationManagers/StockMovementsRelationManager.php',
    'recent stock movements widget' => 'app/Filament/Warehouse/Widgets/RecentStockMovementsTable.php',
    'stock transfers table' => 'app/Filament/Resources/StockTransfers/Tables/StockTransfersTable.php',
]);

it('shows audit timestamps with time when displayed', function (string $path): void {
    expect(file_get_contents(base_path($path)))
        ->toContain('->dateTime')
        ->not->toContain("created_at')\n                    ->date()")
        ->not->toContain("updated_at')\n                    ->date()")
        ->not->toContain("voided_at')\n                    ->date()");
})->with([
    'journal entry infolist' => 'app/Filament/Resources/JournalEntries/Schemas/JournalEntryInfolist.php',
    'production reports table' => 'app/Filament/Resources/ProductionReports/Tables/ProductionReportsTable.php',
    'receiving discrepancies widget' => 'app/Filament/Warehouse/Widgets/ReceivingDiscrepancies.php',
]);

it('does not display midnight as a captured time', function (): void {
    expect(DateTimeDisplay::dateOrDateTime('2026-05-16 00:00:00'))
        ->toBe('16 May 2026')
        ->and(DateTimeDisplay::dateOrDateTime('2026-05-16 14:45:00'))
        ->toBe('16 May 2026, 02:45 PM');
});

it('preserves stock transfer time', function (): void {
    $transfer = new StockTransfer([
        'transfer_date' => '2026-05-16 14:45:00',
    ]);

    expect($transfer->transfer_date->format('H:i'))
        ->toBe('14:45')
        ->and(file_get_contents(base_path('app/Filament/Resources/StockTransfers/Schemas/StockTransferForm.php')))
        ->toContain(DateTimePicker::class);
});

it('filters stock movements by movement date', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('warehouse'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Admin,
    ]));

    $warehouse = Warehouse::factory()->create(['name' => 'Main Store']);
    $includedItem = InventoryItem::factory()->create(['name' => 'Included Paper']);
    $excludedItem = InventoryItem::factory()->create(['name' => 'Excluded Ink']);

    StockMovement::factory()->create([
        'inventory_item_id' => $includedItem->id,
        'warehouse_id' => $warehouse->id,
        'movement_date' => '2026-05-10 14:45:00',
    ]);

    StockMovement::factory()->create([
        'inventory_item_id' => $excludedItem->id,
        'warehouse_id' => $warehouse->id,
        'movement_date' => '2026-05-12 09:00:00',
    ]);

    Livewire::test(ListStockMovements::class)
        ->filterTable('movement_date_range', [
            'movement_date' => '2026-05-10 - 2026-05-10',
        ])
        ->assertSee('Included Paper')
        ->assertDontSee('Excluded Ink');
});

it('shows stock movement references as readable source documents', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('warehouse'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Admin,
    ]));

    $jobOrder = JobOrder::factory()->create([
        'job_order_number' => 'JO-STOCK-001',
    ]);

    $movement = StockMovement::factory()->create([
        'inventory_item_id' => InventoryItem::factory()->create(['unit' => 'Reem'])->id,
        'quantity' => 123.40,
        'unit_cost' => 45.50,
        'reference_type' => JobOrder::class,
        'reference_id' => $jobOrder->id,
    ]);

    Livewire::test(ViewStockMovement::class, ['record' => $movement->id])
        ->assertSuccessful()
        ->assertSee('123.40')
        ->assertSee('Price per Reem')
        ->assertSee('Job Order, JO-STOCK-001')
        ->assertDontSee(JobOrder::class);
});

it('gives finance read only access to stock movements', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    $movement = StockMovement::factory()->create();

    expect(StockMovementResource::canViewAny())->toBeTrue()
        ->and(StockMovementResource::canCreate())->toBeFalse();

    Livewire::test(ListStockMovements::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$movement]);

    Livewire::test(ViewStockMovement::class, ['record' => $movement->id])
        ->assertSuccessful();
});

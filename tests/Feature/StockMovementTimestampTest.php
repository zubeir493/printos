<?php

use App\Filament\Resources\StockMovements\Schemas\StockMovementForm;
use App\Models\StockTransfer;
use App\Support\DateTimeDisplay;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Schema;

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

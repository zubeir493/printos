<?php

use App\Filament\Exports\InventoryItemExporter;
use App\Filament\Exports\JobOrderExporter;
use App\Filament\Exports\PurchaseOrderExporter;
use App\Filament\Exports\SalesOrderExporter;
use Filament\Actions\Exports\Exporter;
use Illuminate\Support\Facades\File;

test('filament exporters complete synchronously so download notifications are sent immediately', function (): void {
    $exporterClasses = collect(File::files(app_path('Filament/Exports')))
        ->map(fn (SplFileInfo $file): string => 'App\\Filament\\Exports\\'.$file->getBasename('.php'))
        ->filter(fn (string $class): bool => is_subclass_of($class, Exporter::class));

    expect($exporterClasses)->not->toBeEmpty();

    $exporterClasses->each(function (string $class): void {
        expect(file_get_contents((new ReflectionClass($class))->getFileName()))
            ->toContain('use RunsExportsSynchronously;');
    });
});

test('order exporters include financial totals and payment balances', function (string $exporterClass): void {
    $columnNames = collect($exporterClass::getColumns())
        ->map(fn ($column): string => $column->getName());

    expect($columnNames)
        ->toContain('subtotal')
        ->toContain('tax_amount')
        ->toContain('total')
        ->toContain('paid_amount')
        ->toContain('balance');
})->with([
    'sales orders' => SalesOrderExporter::class,
    'purchase orders' => PurchaseOrderExporter::class,
    'job orders' => JobOrderExporter::class,
]);

test('inventory item exporter includes all item fields', function (): void {
    $columnNames = collect(InventoryItemExporter::getColumns())
        ->map(fn ($column): string => $column->getName());

    expect($columnNames)
        ->toContain(
            'id',
            'name',
            'image',
            'sku',
            'unit',
            'purchase_unit',
            'conversion_factor',
            'type',
            'category',
            'is_sellable',
            'price',
            'average_cost',
            'low_stock_threshold',
            'gsm',
            'width',
            'height',
            'default_waste_percent',
            'created_at',
            'updated_at',
        );
});

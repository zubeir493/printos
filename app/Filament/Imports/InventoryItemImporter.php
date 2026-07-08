<?php

namespace App\Filament\Imports;

use App\Models\InventoryItem;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

class InventoryItemImporter extends Importer
{
    protected static ?string $model = InventoryItem::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('image')
                ->rules(['max:255']),
            ImportColumn::make('sku')
                ->label('SKU')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('unit')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('average_cost')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
            ImportColumn::make('low_stock_threshold')
                ->numeric()
                ->rules(['integer']),
            ImportColumn::make('purchase_unit')
                ->rules(['max:255']),
            ImportColumn::make('conversion_factor')
                ->numeric()
                ->rules(['integer']),
            ImportColumn::make('type')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('category')
                ->rules(['max:255']),
            ImportColumn::make('is_sellable')
                ->requiredMapping()
                ->boolean()
                ->rules(['required', 'boolean']),
            ImportColumn::make('price')
                ->numeric()
                ->rules(['integer']),
            ImportColumn::make('gsm')
                ->numeric()
                ->rules(['integer']),
            ImportColumn::make('width')
                ->numeric()
                ->rules(['integer']),
            ImportColumn::make('height')
                ->numeric()
                ->rules(['integer']),
            ImportColumn::make('default_waste_percent')
                ->numeric()
                ->rules(['integer']),
        ];
    }

    public function resolveRecord(): InventoryItem
    {
        return new InventoryItem;
    }

    public function getJobConnection(): ?string
    {
        return 'sync';
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your inventory item import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}

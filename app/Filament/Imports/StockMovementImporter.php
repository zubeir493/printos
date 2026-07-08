<?php

namespace App\Filament\Imports;

use App\Models\StockMovement;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

class StockMovementImporter extends Importer
{
    protected static ?string $model = StockMovement::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('inventoryItem')
                ->requiredMapping()
                ->relationship()
                ->rules(['required']),
            ImportColumn::make('warehouse')
                ->requiredMapping()
                ->relationship()
                ->rules(['required']),
            ImportColumn::make('type')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('reference_type')
                ->rules(['max:255']),
            ImportColumn::make('reference_id')
                ->numeric()
                ->rules(['integer']),
            ImportColumn::make('quantity')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
            ImportColumn::make('unit_cost')
                ->numeric()
                ->rules(['integer']),
            ImportColumn::make('total_cost')
                ->numeric()
                ->rules(['integer']),
            ImportColumn::make('movement_date')
                ->requiredMapping()
                ->rules(['required', 'datetime']),
        ];
    }

    public function resolveRecord(): StockMovement
    {
        return new StockMovement;
    }

    public function getJobConnection(): ?string
    {
        return 'sync';
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your stock movement import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}

<?php

namespace App\Filament\Imports;

use App\Models\StockAdjustment;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

class StockAdjustmentImporter extends Importer
{
    protected static ?string $model = StockAdjustment::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('adjustment_number')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('warehouse')
                ->requiredMapping()
                ->relationship()
                ->rules(['required']),
            ImportColumn::make('adjustment_date')
                ->requiredMapping()
                ->rules(['required', 'date']),
            ImportColumn::make('status')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('reason')
                ->rules(['max:255']),
            ImportColumn::make('created_by')
                ->numeric()
                ->rules(['integer']),
            ImportColumn::make('posted_at')
                ->rules(['datetime']),
        ];
    }

    public function resolveRecord(): StockAdjustment
    {
        return new StockAdjustment;
    }

    public function getJobConnection(): ?string
    {
        return 'sync';
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your stock adjustment import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}

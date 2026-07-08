<?php

namespace App\Filament\Imports;

use App\Models\SalesOrder;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

class SalesOrderImporter extends Importer
{
    protected static ?string $model = SalesOrder::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('order_number')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('warehouse')
                ->requiredMapping()
                ->relationship()
                ->rules(['required']),
            ImportColumn::make('partner')
                ->relationship(),
            ImportColumn::make('order_date')
                ->requiredMapping()
                ->rules(['required', 'date']),
            ImportColumn::make('due_date')
                ->rules(['date']),
            ImportColumn::make('payment_mode')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('payment_method')
                ->rules(['max:255']),
            ImportColumn::make('payment_reference')
                ->rules(['max:255']),
            ImportColumn::make('subtotal')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
            ImportColumn::make('tax_amount')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
            ImportColumn::make('total')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
            ImportColumn::make('status')
                ->requiredMapping()
                ->rules(['required']),
        ];
    }

    public function resolveRecord(): SalesOrder
    {
        return new SalesOrder;
    }

    public function getJobConnection(): ?string
    {
        return 'sync';
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your sales order import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}

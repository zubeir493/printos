<?php

namespace App\Filament\Imports;

use App\Models\BankTransaction;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

class BankTransactionImporter extends Importer
{
    protected static ?string $model = BankTransaction::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('bank')
                ->relationship(),
            ImportColumn::make('source_id')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
            ImportColumn::make('source_type')
                ->requiredMapping()
                ->rules(['required', 'max:13']),
            ImportColumn::make('transaction_number')
                ->rules(['max:260']),
            ImportColumn::make('transaction_type')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('direction')
                ->requiredMapping()
                ->rules(['required', 'max:8']),
            ImportColumn::make('amount')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
            ImportColumn::make('balance_delta')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
            ImportColumn::make('transaction_date')
                ->rules(['date']),
            ImportColumn::make('status')
                ->requiredMapping()
                ->rules(['required', 'max:9']),
            ImportColumn::make('reference')
                ->rules(['max:255']),
            ImportColumn::make('counterparty')
                ->rules(['max:255']),
            ImportColumn::make('related_bank_name')
                ->rules(['max:255']),
        ];
    }

    public function resolveRecord(): BankTransaction
    {
        return new BankTransaction;
    }

    public function getJobConnection(): ?string
    {
        return 'sync';
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your bank transaction import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}

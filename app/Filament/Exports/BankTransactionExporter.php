<?php

namespace App\Filament\Exports;

use App\Models\BankTransaction;
use App\Support\Money;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class BankTransactionExporter extends Exporter
{
    use RunsExportsSynchronously;

    protected static ?string $model = BankTransaction::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('transaction_number')
                ->label('Transaction'),
            ExportColumn::make('bank.name')
                ->label('Bank'),
            ExportColumn::make('source_type')
                ->label('Source')
                ->formatStateUsing(fn (string $state): string => str($state)->replace('_', ' ')->headline()->toString()),
            ExportColumn::make('transaction_type')
                ->label('Type')
                ->formatStateUsing(fn (string $state): string => str($state)->replace('_', ' ')->headline()->toString()),
            ExportColumn::make('direction')
                ->label('Direction'),
            ExportColumn::make('balance_delta')
                ->label('Balance Change')
                ->formatStateUsing(fn ($state): string => Money::format($state)),
            ExportColumn::make('counterparty')
                ->label('Counterparty'),
            ExportColumn::make('related_bank_name')
                ->label('Related Bank'),
            ExportColumn::make('transaction_date')
                ->label('Date'),
            ExportColumn::make('reference')
                ->label('Reference'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your bank transaction export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}

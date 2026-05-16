<?php

namespace App\Filament\Exports;

use App\Models\Account;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class FinancialAccountExporter extends Exporter
{
    use RunsExportsSynchronously;

    protected static ?string $model = Account::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('code')->label('Code'),
            ExportColumn::make('name')->label('Account'),
            ExportColumn::make('type')->label('Type'),
            ExportColumn::make('debit_total')->label('Debit'),
            ExportColumn::make('credit_total')->label('Credit'),
            ExportColumn::make('balance')->label('Balance'),
            ExportColumn::make('display_amount')->label('Amount'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Your finance report export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';
    }
}

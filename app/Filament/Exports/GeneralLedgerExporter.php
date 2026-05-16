<?php

namespace App\Filament\Exports;

use App\Models\JournalItem;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class GeneralLedgerExporter extends Exporter
{
    use RunsExportsSynchronously;

    protected static ?string $model = JournalItem::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('account_code')->label('Account Code'),
            ExportColumn::make('account_name')->label('Account Name'),
            ExportColumn::make('account_type')->label('Account Type'),
            ExportColumn::make('entry_date')->label('Date'),
            ExportColumn::make('entry_reference')->label('Reference'),
            ExportColumn::make('entry_narration')->label('Narration'),
            ExportColumn::make('debit')->label('Debit'),
            ExportColumn::make('credit')->label('Credit'),
            ExportColumn::make('running_balance')->label('Running Balance'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Your general ledger export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';
    }
}

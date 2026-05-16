<?php

namespace App\Filament\Exports;

use App\Models\JournalEntry;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class JournalEntryExporter extends Exporter
{
    use RunsExportsSynchronously;

    protected static ?string $model = JournalEntry::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('date')
                ->label('Date'),
            ExportColumn::make('reference')
                ->label('Reference'),
            ExportColumn::make('narration')
                ->label('Description'),
            ExportColumn::make('total_debit')
                ->label('Total Debit'),
            ExportColumn::make('total_credit')
                ->label('Total Credit'),
            ExportColumn::make('status')
                ->label('Status'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your journal entry export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}

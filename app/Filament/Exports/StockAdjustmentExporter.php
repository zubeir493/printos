<?php

namespace App\Filament\Exports;

use App\Models\StockAdjustment;
use App\Support\DateTimeDisplay;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class StockAdjustmentExporter extends Exporter
{
    use RunsExportsSynchronously;

    protected static ?string $model = StockAdjustment::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('adjustment_number')
                ->label('Adjustment Number'),
            ExportColumn::make('warehouse.name')
                ->label('Warehouse'),
            ExportColumn::make('adjustment_date')
                ->label('Adjustment Date')
                ->formatStateUsing(fn ($state) => DateTimeDisplay::dateOrDateTime($state)),
            ExportColumn::make('status')
                ->label('Status'),
            ExportColumn::make('reason')
                ->label('Reason'),
            ExportColumn::make('creator.name')
                ->label('Created By'),
            ExportColumn::make('posted_at')
                ->label('Posted At')
                ->formatStateUsing(fn ($state) => DateTimeDisplay::dateOrDateTime($state)),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your stock adjustment export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}

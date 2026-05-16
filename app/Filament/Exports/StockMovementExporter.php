<?php

namespace App\Filament\Exports;

use App\Models\StockMovement;
use App\Support\DateTimeDisplay;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class StockMovementExporter extends Exporter
{
    use RunsExportsSynchronously;

    protected static ?string $model = StockMovement::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('inventoryItem.name')
                ->label('Item'),
            ExportColumn::make('warehouse.name')
                ->label('Warehouse'),
            ExportColumn::make('type')
                ->label('Movement Type'),
            ExportColumn::make('quantity')
                ->label('Quantity'),
            ExportColumn::make('unit_cost')
                ->label('Unit Cost'),
            ExportColumn::make('total_cost')
                ->label('Total Cost'),
            ExportColumn::make('movement_date')
                ->label('Moved At')
                ->formatStateUsing(fn ($state) => DateTimeDisplay::dateOrDateTime($state)),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your stock movement export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}

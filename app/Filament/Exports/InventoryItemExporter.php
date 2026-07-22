<?php

namespace App\Filament\Exports;

use App\Models\InventoryItem;
use App\Support\DateTimeDisplay;
use App\Support\Money;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class InventoryItemExporter extends Exporter
{
    use RunsExportsSynchronously;

    protected static ?string $model = InventoryItem::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('name')
                ->label('Item Name'),
            ExportColumn::make('image')
                ->label('Image'),
            ExportColumn::make('sku')
                ->label('SKU'),
            ExportColumn::make('unit')
                ->label('Base Unit'),
            ExportColumn::make('purchase_unit')
                ->label('Purchase Unit'),
            ExportColumn::make('conversion_factor')
                ->label('Conversion Factor'),
            ExportColumn::make('type')
                ->label('Type'),
            ExportColumn::make('category')
                ->label('Category'),
            ExportColumn::make('is_sellable')
                ->label('Sellable')
                ->formatStateUsing(fn ($state): string => $state ? 'Yes' : 'No'),
            ExportColumn::make('price')
                ->label('Price / Value ('.Money::suffix().')'),
            ExportColumn::make('average_cost')
                ->label('Average Cost ('.Money::suffix().')'),
            ExportColumn::make('low_stock_threshold')
                ->label('Low Stock Threshold'),
            ExportColumn::make('gsm')
                ->label('GSM'),
            ExportColumn::make('width')
                ->label('Width'),
            ExportColumn::make('height')
                ->label('Height'),
            ExportColumn::make('default_waste_percent')
                ->label('Default Waste Percent'),
            ExportColumn::make('created_at')
                ->label('Created At')
                ->formatStateUsing(fn ($state) => DateTimeDisplay::dateOrDateTime($state)),
            ExportColumn::make('updated_at')
                ->label('Updated At')
                ->formatStateUsing(fn ($state) => DateTimeDisplay::dateOrDateTime($state)),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your inventory item export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}

<?php

namespace App\Filament\Exports;

use App\Models\SalesOrder;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class ReceivablesAgingExporter extends Exporter
{
    use RunsExportsSynchronously;

    protected static ?string $model = SalesOrder::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('order_number')->label('Document #'),
            ExportColumn::make('partner.name')->label('Customer'),
            ExportColumn::make('order_date')->label('Date'),
            ExportColumn::make('balance')->label('Outstanding'),
            ExportColumn::make('age_days')->label('Age (Days)'),
            ExportColumn::make('bucket')->label('Bucket'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Your receivables aging export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';
    }
}

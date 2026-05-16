<?php

namespace App\Filament\Exports;

use App\Models\PurchaseOrder;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class PayablesAgingExporter extends Exporter
{
    use RunsExportsSynchronously;

    protected static ?string $model = PurchaseOrder::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('po_number')->label('Document #'),
            ExportColumn::make('partner.name')->label('Vendor'),
            ExportColumn::make('order_date')->label('Date'),
            ExportColumn::make('balance')->label('Outstanding'),
            ExportColumn::make('age_days')->label('Age (Days)'),
            ExportColumn::make('bucket')->label('Bucket'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Your payables aging export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';
    }
}

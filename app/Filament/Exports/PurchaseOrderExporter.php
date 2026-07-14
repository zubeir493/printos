<?php

namespace App\Filament\Exports;

use App\Models\PurchaseOrder;
use App\Support\Money;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class PurchaseOrderExporter extends Exporter
{
    use RunsExportsSynchronously;

    protected static ?string $model = PurchaseOrder::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('po_number')
                ->label('PO Number'),
            ExportColumn::make('partner.name')
                ->label('Supplier'),
            ExportColumn::make('order_date')
                ->label('Order Date'),
            ExportColumn::make('subtotal')
                ->label('Subtotal ('.Money::suffix().')'),
            ExportColumn::make('tax_amount')
                ->label('Tax Amount ('.Money::suffix().')'),
            ExportColumn::make('total')
                ->label('Total ('.Money::suffix().')'),
            ExportColumn::make('paid_amount')
                ->label('Paid Amount ('.Money::suffix().')'),
            ExportColumn::make('balance')
                ->label('Balance ('.Money::suffix().')'),
            ExportColumn::make('status')
                ->label('Status'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your purchase order export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}

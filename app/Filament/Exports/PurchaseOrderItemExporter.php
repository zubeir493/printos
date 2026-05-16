<?php

namespace App\Filament\Exports;

use App\Models\PurchaseOrderItem;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class PurchaseOrderItemExporter extends Exporter
{
    use RunsExportsSynchronously;

    protected static ?string $model = PurchaseOrderItem::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('purchaseOrder.po_number')
                ->label('PO #'),
            ExportColumn::make('purchaseOrder.partner.name')
                ->label('Supplier'),
            ExportColumn::make('inventoryItem.name')
                ->label('Item'),
            ExportColumn::make('quantity')
                ->label('Order Qty'),
            ExportColumn::make('received_quantity')
                ->label('Received Qty'),
            ExportColumn::make('unit_price')
                ->label('Unit Price'),
            ExportColumn::make('total')
                ->label('Total'),
            ExportColumn::make('status')
                ->label('Status'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your purchase order item export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}

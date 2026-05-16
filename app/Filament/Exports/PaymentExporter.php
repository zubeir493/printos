<?php

namespace App\Filament\Exports;

use App\Models\Payment;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class PaymentExporter extends Exporter
{
    use RunsExportsSynchronously;

    protected static ?string $model = Payment::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('payment_number')
                ->label('Payment #'),
            ExportColumn::make('partner.name')
                ->label('Partner'),
            ExportColumn::make('payment_date')
                ->label('Date'),
            ExportColumn::make('amount')
                ->label('Amount'),
            ExportColumn::make('direction')
                ->label('Direction'),
            ExportColumn::make('method')
                ->label('Method'),
            ExportColumn::make('reference')
                ->label('Reference'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your payment export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}

<?php

namespace App\Filament\Exports;

use App\Models\PaymentAllocation;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class PaymentAllocationExporter extends Exporter
{
    use RunsExportsSynchronously;

    protected static ?string $model = PaymentAllocation::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('payment.payment_number')
                ->label('Payment #'),
            ExportColumn::make('payment.partner.name')
                ->label('Partner'),
            ExportColumn::make('payment.payment_date')
                ->label('Payment Date'),
            ExportColumn::make('payment.direction')
                ->label('Direction'),
            ExportColumn::make('payment.method')
                ->label('Payment Method'),
            ExportColumn::make('allocatable_type')
                ->label('Allocated To (Type)')
                ->formatStateUsing(fn ($state) => class_basename($state)),
            ExportColumn::make('allocatable_id')
                ->label('Document ID'),
            ExportColumn::make('allocated_amount')
                ->label('Allocated Amount'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your payment allocation export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}

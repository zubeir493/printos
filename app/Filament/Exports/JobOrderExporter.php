<?php

namespace App\Filament\Exports;

use App\Models\JobOrder;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class JobOrderExporter extends Exporter
{
    use RunsExportsSynchronously;

    protected static ?string $model = JobOrder::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('job_order_number')
                ->label('Job Order #'),
            ExportColumn::make('partner.name')
                ->label('Customer'),
            ExportColumn::make('job_type')
                ->label('Type'),
            ExportColumn::make('submission_date')
                ->label('Submission Date'),
            ExportColumn::make('subtotal')
                ->label('Subtotal (Birr)'),
            ExportColumn::make('tax_amount')
                ->label('Tax Amount (Birr)'),
            ExportColumn::make('total')
                ->label('Total (Birr)'),
            ExportColumn::make('paid_amount')
                ->label('Paid Amount (Birr)'),
            ExportColumn::make('balance')
                ->label('Balance (Birr)'),
            ExportColumn::make('status')
                ->label('Status'),
            ExportColumn::make('production_started_at'),
            ExportColumn::make('materials_fully_issued_at'),
            ExportColumn::make('advance_paid')
                ->label('Advance Paid')
                ->getStateUsing(fn ($record) => $record->paymentAllocations()->exists() ? 'Yes' : 'No'),
            ExportColumn::make('advance_amount')
                ->label('Advance Amount (Birr)'),
            ExportColumn::make('production_mode'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your job order export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}

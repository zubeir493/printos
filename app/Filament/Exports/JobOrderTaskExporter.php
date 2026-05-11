<?php

namespace App\Filament\Exports;

use App\Models\JobOrderTask;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class JobOrderTaskExporter extends Exporter
{
    protected static ?string $model = JobOrderTask::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('jobOrder.job_order_number')
                ->label('Job Order #'),
            ExportColumn::make('jobOrder.partner.name')
                ->label('Customer'),
            ExportColumn::make('name')
                ->label('Task Name'),
            ExportColumn::make('quantity')
                ->label('Quantity'),
            ExportColumn::make('task_cost')
                ->label('Task Cost'),
            ExportColumn::make('jobOrder.status')
                ->label('Order Status'),
            ExportColumn::make('status')
                ->label('Task Status'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your job order task export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}

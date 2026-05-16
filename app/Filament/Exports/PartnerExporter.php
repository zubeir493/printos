<?php

namespace App\Filament\Exports;

use App\Models\Partner;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class PartnerExporter extends Exporter
{
    use RunsExportsSynchronously;

    protected static ?string $model = Partner::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('name')
                ->label('Name'),
            ExportColumn::make('phone')
                ->label('Phone'),
            ExportColumn::make('address')
                ->label('Address'),
            ExportColumn::make('is_customer')
                ->label('Is Customer')
                ->state(fn ($record) => $record->is_customer ? 'Yes' : 'No'),
            ExportColumn::make('is_supplier')
                ->label('Is Supplier')
                ->state(fn ($record) => $record->is_supplier ? 'Yes' : 'No'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your partner export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}

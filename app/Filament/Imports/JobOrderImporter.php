<?php

namespace App\Filament\Imports;

use App\Models\JobOrder;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

class JobOrderImporter extends Importer
{
    protected static ?string $model = JobOrder::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('job_order_number')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('partner')
                ->relationship(),
            ImportColumn::make('job_type')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('cost_calc_file')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('services')
                ->requiredMapping()
                ->rules(['required']),
            ImportColumn::make('submission_date')
                ->requiredMapping()
                ->rules(['required', 'date']),
            ImportColumn::make('due_date')
                ->rules(['date']),
            ImportColumn::make('remarks'),
            ImportColumn::make('advance_amount')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
            ImportColumn::make('subtotal')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
            ImportColumn::make('advance_paid')
                ->requiredMapping()
                ->boolean()
                ->rules(['required', 'boolean']),
            ImportColumn::make('tax_amount')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
            ImportColumn::make('total')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
            ImportColumn::make('status')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('production_started_at')
                ->rules(['datetime']),
            ImportColumn::make('notified_late_at')
                ->rules(['datetime']),
            ImportColumn::make('materials_fully_issued_at')
                ->rules(['datetime']),
            ImportColumn::make('production_mode')
                ->requiredMapping()
                ->rules(['required']),
        ];
    }

    public function resolveRecord(): JobOrder
    {
        return new JobOrder;
    }

    public function getJobConnection(): ?string
    {
        return 'sync';
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your job order import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}

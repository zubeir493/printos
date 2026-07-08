<?php

namespace App\Filament\Imports;

use App\Models\JobOrderTask;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

class JobOrderTaskImporter extends Importer
{
    protected static ?string $model = JobOrderTask::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('jobOrder')
                ->requiredMapping()
                ->relationship()
                ->rules(['required']),
            ImportColumn::make('designer')
                ->relationship(),
            ImportColumn::make('typist')
                ->relationship(),
            ImportColumn::make('instructions'),
            ImportColumn::make('deliverables'),
            ImportColumn::make('name')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('quantity')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
            ImportColumn::make('task_cost')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
            ImportColumn::make('paper'),
            ImportColumn::make('status')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('size')
                ->rules(['max:255']),
        ];
    }

    public function resolveRecord(): JobOrderTask
    {
        return new JobOrderTask;
    }

    public function getJobConnection(): ?string
    {
        return 'sync';
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your job order task import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}

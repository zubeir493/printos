<?php

namespace App\Filament\Imports;

use App\Models\JournalEntry;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

class JournalEntryImporter extends Importer
{
    protected static ?string $model = JournalEntry::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('date')
                ->requiredMapping()
                ->rules(['required', 'date']),
            ImportColumn::make('reference')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('source_type')
                ->rules(['max:255']),
            ImportColumn::make('source_id')
                ->numeric()
                ->rules(['integer']),
            ImportColumn::make('attachment')
                ->rules(['max:255']),
            ImportColumn::make('narration'),
            ImportColumn::make('total_debit')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
            ImportColumn::make('total_credit')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
            ImportColumn::make('status')
                ->requiredMapping()
                ->rules(['required']),
            ImportColumn::make('posted_at')
                ->rules(['datetime']),
            ImportColumn::make('voided_at')
                ->rules(['datetime']),
            ImportColumn::make('reversal_of_journal_entry_id')
                ->numeric()
                ->rules(['integer']),
        ];
    }

    public function resolveRecord(): JournalEntry
    {
        return new JournalEntry;
    }

    public function getJobConnection(): ?string
    {
        return 'sync';
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your journal entry import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}

<?php

namespace App\Filament\Resources\JournalEntries\Pages;

use App\Filament\Exports\JournalEntryExporter;
use App\Filament\Resources\JournalEntries\JournalEntryResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListJournalEntries extends ListRecords
{
    protected static string $resource = JournalEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ActionGroup::make([
                ExportAction::make()
                    ->exporter(JournalEntryExporter::class),
            ]),
        ];
    }
}

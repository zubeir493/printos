<?php

namespace App\Filament\Resources\JobOrderTasks\Pages;

use App\Filament\Exports\JobOrderTaskExporter;
use App\Filament\Resources\JobOrderTasks\JobOrderTaskResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\ExportAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;

class ListJobOrderTasks extends ListRecords
{
    protected static string $resource = JobOrderTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                ExportAction::make()
                    ->exporter(JobOrderTaskExporter::class)
                    ->visible(fn () => in_array(Filament::getCurrentPanel()?->getId(), ['admin', 'operations', 'finance'])),
            ]),
        ];
    }
}

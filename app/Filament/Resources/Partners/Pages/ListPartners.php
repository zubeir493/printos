<?php

namespace App\Filament\Resources\Partners\Pages;

use App\Filament\Exports\PartnerExporter;
use App\Filament\Resources\Partners\PartnerResource;
use App\Filament\Support\PanelAccess;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListPartners extends ListRecords
{
    protected static string $resource = PartnerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn () => PanelAccess::canManagePartners()),
            ActionGroup::make([
                ExportAction::make()
                    ->exporter(PartnerExporter::class),
            ]),
        ];
    }
}

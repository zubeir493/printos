<?php

namespace App\Filament\Resources\Accounts\Pages;

use App\Filament\Exports\AccountExporter;
use App\Filament\Resources\Accounts\AccountResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListAccounts extends ListRecords
{
    protected static string $resource = AccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ActionGroup::make([
                ExportAction::make()
                    ->exporter(AccountExporter::class),
            ]),
        ];
    }
}

<?php

namespace App\Filament\Resources\SalesOrders\Pages;

use App\Filament\Exports\SalesOrderExporter;
use App\Filament\Resources\SalesOrders\SalesOrderResource;
use App\Filament\Support\PanelAccess;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListSalesOrders extends ListRecords
{
    protected static string $resource = SalesOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn () => PanelAccess::canManageSalesOrders()),
            ActionGroup::make([
                ExportAction::make()
                    ->exporter(SalesOrderExporter::class),
            ]),
        ];
    }
}

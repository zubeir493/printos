<?php

namespace App\Filament\Resources\InventoryItems\Pages;

use App\Filament\Exports\InventoryItemExporter;
use App\Filament\Resources\InventoryItems\InventoryItemResource;
use App\Filament\Widgets\StockOverviewStats;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListInventoryItems extends ListRecords
{
    protected static string $resource = InventoryItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ActionGroup::make([
                ExportAction::make()
                    ->exporter(InventoryItemExporter::class),
            ]),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            StockOverviewStats::class,
        ];
    }
}

<?php

namespace App\Filament\Resources\StockMovements\Pages;

use App\Filament\Exports\StockMovementExporter;
use App\Filament\Resources\StockMovements\StockMovementResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListStockMovements extends ListRecords
{
    protected static string $resource = StockMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                ExportAction::make()
                    ->exporter(StockMovementExporter::class),
            ]),
        ];
    }
}

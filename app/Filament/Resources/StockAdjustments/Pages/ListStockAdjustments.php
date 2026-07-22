<?php

namespace App\Filament\Resources\StockAdjustments\Pages;

use App\Filament\Exports\StockAdjustmentExporter;
use App\Filament\Imports\StockAdjustmentImporter;
use App\Filament\Resources\StockAdjustments\StockAdjustmentResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListStockAdjustments extends ListRecords
{
    protected static string $resource = StockAdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ActionGroup::make([
                ImportAction::make()
                    ->importer(StockAdjustmentImporter::class),
                ExportAction::make()
                    ->exporter(StockAdjustmentExporter::class),
            ]),
        ];
    }
}

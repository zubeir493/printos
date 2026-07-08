<?php

namespace App\Filament\Resources\PurchaseOrderItems\Pages;

use App\Filament\Exports\PurchaseOrderItemExporter;
use App\Filament\Resources\PurchaseOrderItems\PurchaseOrderItemResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListPurchaseOrderItems extends ListRecords
{
    protected static string $resource = PurchaseOrderItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                ExportAction::make()
                    ->exporter(PurchaseOrderItemExporter::class),
            ]),
        ];
    }
}

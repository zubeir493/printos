<?php

namespace App\Filament\Resources\PurchaseOrders\Pages;

use App\Filament\Exports\PurchaseOrderExporter;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Support\PanelAccess;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListPurchaseOrders extends ListRecords
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn () => PanelAccess::canManagePurchaseOrders()),
            ActionGroup::make([
                ExportAction::make()
                    ->exporter(PurchaseOrderExporter::class),
            ]),
        ];
    }
}

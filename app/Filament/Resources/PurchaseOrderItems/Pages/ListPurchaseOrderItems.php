<?php

namespace App\Filament\Resources\PurchaseOrderItems\Pages;

use App\Filament\Resources\PurchaseOrderItems\PurchaseOrderItemResource;
use Filament\Resources\Pages\ListRecords;

class ListPurchaseOrderItems extends ListRecords
{
    protected static string $resource = PurchaseOrderItemResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}

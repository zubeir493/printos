<?php

namespace App\Filament\Resources\PurchaseOrderItems\Pages;

use App\Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\PurchaseOrderItems\PurchaseOrderItemResource;

class CreatePurchaseOrderItem extends CreateRecord
{
    protected static string $resource = PurchaseOrderItemResource::class;
}

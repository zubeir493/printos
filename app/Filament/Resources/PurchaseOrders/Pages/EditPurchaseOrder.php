<?php

namespace App\Filament\Resources\PurchaseOrders\Pages;

use App\Filament\Resources\PurchaseOrders\Actions\PurchaseOrderActions;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Support\PanelAccess;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditPurchaseOrder extends EditRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    public static function canAccess($record = null): bool
    {
        return PanelAccess::canManagePurchaseOrders();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Edit '.$this->getRecord()->po_number;
    }

    protected function getHeaderActions(): array
    {
        return [
            PurchaseOrderActions::make(includeDelete: true),
        ];
    }
}

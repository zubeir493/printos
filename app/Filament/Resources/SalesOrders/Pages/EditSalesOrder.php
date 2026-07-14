<?php

namespace App\Filament\Resources\SalesOrders\Pages;

use App\Filament\Resources\SalesOrders\SalesOrderResource;
use App\Filament\Support\PanelAccess;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditSalesOrder extends EditRecord
{
    protected static string $resource = SalesOrderResource::class;

    public static function canAccess($record = null): bool
    {
        return PanelAccess::canManageSalesOrders();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Edit '.$this->getRecord()->order_number;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->color('gray'),
        ];
    }
}

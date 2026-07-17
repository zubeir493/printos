<?php

namespace App\Filament\Resources\SalesOrders\Pages;

use App\Filament\Resources\SalesOrders\Actions\SalesOrderActions;
use App\Filament\Resources\SalesOrders\SalesOrderResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewSalesOrder extends ViewRecord
{
    protected static string $resource = SalesOrderResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->getRecord()->order_number;
    }

    protected function getHeaderActions(): array
    {
        return [
            SalesOrderActions::make(),
        ];
    }
}

<?php

namespace App\Filament\Resources\StockAdjustments\Pages;

use App\Filament\Resources\StockAdjustments\Actions\StockAdjustmentActions;
use App\Filament\Resources\StockAdjustments\StockAdjustmentResource;
use Filament\Resources\Pages\EditRecord;

class EditStockAdjustment extends EditRecord
{
    protected static string $resource = StockAdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            StockAdjustmentActions::make(includeDelete: true),
        ];
    }
}

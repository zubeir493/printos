<?php

namespace App\Filament\Resources\ExpenseTrackingItems\Pages;

use App\Filament\Resources\ExpenseTrackingItems\ExpenseTrackingItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageExpenseTrackingItems extends ManageRecords
{
    protected static string $resource = ExpenseTrackingItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

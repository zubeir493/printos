<?php

namespace App\Filament\Resources\ProductionReports\Pages;

use App\Filament\Resources\ProductionReports\ProductionReportResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProductionReport extends EditRecord
{
    protected static string $resource = ProductionReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn ($record) => $record->status === 'draft'),
        ];
    }
}

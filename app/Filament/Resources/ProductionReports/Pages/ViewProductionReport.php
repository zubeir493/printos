<?php

namespace App\Filament\Resources\ProductionReports\Pages;

use App\Filament\Resources\ProductionReports\ProductionReportResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProductionReport extends ViewRecord
{
    protected static string $resource = ProductionReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn ($record) => $record->status === 'draft'),
        ];
    }
}

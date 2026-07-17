<?php

namespace App\Filament\Resources\ProductionReports\Pages;

use App\Filament\Resources\ProductionReports\Actions\ProductionReportActions;
use App\Filament\Resources\ProductionReports\ProductionReportResource;
use Filament\Resources\Pages\EditRecord;

class EditProductionReport extends EditRecord
{
    protected static string $resource = ProductionReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ProductionReportActions::make(includeDelete: true),
        ];
    }
}

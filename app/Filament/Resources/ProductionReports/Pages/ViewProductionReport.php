<?php

namespace App\Filament\Resources\ProductionReports\Pages;

use App\Filament\Resources\ProductionReports\Actions\ProductionReportActions;
use App\Filament\Resources\ProductionReports\ProductionReportResource;
use Filament\Resources\Pages\ViewRecord;

class ViewProductionReport extends ViewRecord
{
    protected static string $resource = ProductionReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ProductionReportActions::make(),
        ];
    }
}

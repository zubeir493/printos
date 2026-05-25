<?php

namespace App\Filament\Resources\ProductionReports\Pages;

use App\Filament\Resources\ProductionReports\ProductionReportResource;
use Filament\Resources\Pages\ListRecords;

class ListProductionReports extends ListRecords
{
    protected static string $resource = ProductionReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}

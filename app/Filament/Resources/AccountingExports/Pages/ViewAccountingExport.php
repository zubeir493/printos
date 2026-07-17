<?php

namespace App\Filament\Resources\AccountingExports\Pages;

use App\Filament\Resources\AccountingExports\AccountingExportResource;
use App\Filament\Resources\AccountingExports\Actions\AccountingExportActions;
use Filament\Resources\Pages\ViewRecord;

class ViewAccountingExport extends ViewRecord
{
    protected static string $resource = AccountingExportResource::class;

    protected function getHeaderActions(): array
    {
        return AccountingExportActions::make();
    }
}

<?php

namespace App\Filament\Resources\MaintenanceStoreEntries\Pages;

use App\Filament\Resources\MaintenanceStoreEntries\Actions\MaintenanceStoreActions;
use App\Filament\Resources\MaintenanceStoreEntries\MaintenanceStoreEntryResource;
use Filament\Resources\Pages\ManageRecords;

class ManageMaintenanceStoreEntries extends ManageRecords
{
    protected static string $resource = MaintenanceStoreEntryResource::class;

    protected static ?string $title = 'Maintenance store';

    protected function getHeaderActions(): array
    {
        return [
            MaintenanceStoreActions::checkoutTool(),
            MaintenanceStoreActions::issueSparePart(),
        ];
    }
}

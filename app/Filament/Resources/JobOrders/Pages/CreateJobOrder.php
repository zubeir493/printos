<?php

namespace App\Filament\Resources\JobOrders\Pages;

use App\Filament\Resources\JobOrders\JobOrderResource;
use App\Filament\Resources\Pages\CreateRecord;
use App\Filament\Support\PanelAccess;

class CreateJobOrder extends CreateRecord
{
    protected static string $resource = JobOrderResource::class;

    protected static bool $canCreateAnother = false;

    public static function canAccess($record = null): bool
    {
        return PanelAccess::canManageJobOrders();
    }
}

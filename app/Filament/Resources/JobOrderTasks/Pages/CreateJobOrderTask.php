<?php

namespace App\Filament\Resources\JobOrderTasks\Pages;

use App\Filament\Resources\JobOrderTasks\JobOrderTaskResource;
use App\Filament\Support\PanelAccess;
use Filament\Resources\Pages\CreateRecord;

class CreateJobOrderTask extends CreateRecord
{
    protected static string $resource = JobOrderTaskResource::class;

    public static function canAccess($record = null): bool
    {
        return PanelAccess::canManageJobOrderTasks();
    }
}

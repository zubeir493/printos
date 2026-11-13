<?php

namespace App\Filament\Resources\WorkSchedules;

use App\Filament\Resources\WorkSchedules\Pages\ManageWorkSchedules;
use App\Models\WorkSchedule;
use Filament\Resources\Resource;

class WorkScheduleResource extends Resource
{
    protected static ?string $model = WorkSchedule::class;

    protected static bool $shouldRegisterNavigation = false;

    public static function getPages(): array
    {
        return ['index' => ManageWorkSchedules::route('/')];
    }
}

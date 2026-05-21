<?php

namespace App\Filament\Resources\WorkSchedules\Pages;

use App\Filament\Resources\WorkSchedules\WorkScheduleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageWorkSchedules extends ManageRecords
{
    protected static string $resource = WorkScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

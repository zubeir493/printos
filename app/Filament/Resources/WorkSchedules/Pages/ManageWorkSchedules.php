<?php

namespace App\Filament\Resources\WorkSchedules\Pages;

use App\Filament\Resources\Shifts\ShiftResource;
use App\Filament\Resources\WorkSchedules\WorkScheduleResource;
use Filament\Resources\Pages\ManageRecords;

class ManageWorkSchedules extends ManageRecords
{
    public function mount(): void
    {
        parent::mount();
        $this->redirect(ShiftResource::getUrl());
    }

    protected static string $resource = WorkScheduleResource::class;
}

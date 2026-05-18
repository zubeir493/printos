<?php

namespace App\Filament\Resources\JobOrderTasks\Pages;

use App\Filament\Resources\JobOrderTasks\JobOrderTaskResource;
use Filament\Resources\Pages\ListRecords;

class ListJobOrderTasks extends ListRecords
{
    protected static string $resource = JobOrderTaskResource::class;
}

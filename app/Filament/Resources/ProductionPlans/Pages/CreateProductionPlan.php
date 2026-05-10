<?php

namespace App\Filament\Resources\ProductionPlans\Pages;

use App\Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\ProductionPlans\ProductionPlanResource;

class CreateProductionPlan extends CreateRecord
{
    protected static string $resource = ProductionPlanResource::class;
}

<?php

namespace App\Filament\Resources\ProductionPlans\Pages;

use App\Filament\Resources\ProductionPlans\ProductionPlanResource;
use App\Filament\Resources\Pages\CreateRecord;

class CreateProductionPlan extends CreateRecord
{
    protected static string $resource = ProductionPlanResource::class;
}

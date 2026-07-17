<?php

namespace App\Filament\Resources\Proformas\Pages;

use App\Filament\Resources\Proformas\Actions\ProformaActions;
use App\Filament\Resources\Proformas\ProformaResource;
use Filament\Resources\Pages\ViewRecord;

class ViewProforma extends ViewRecord
{
    protected static string $resource = ProformaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ProformaActions::make(),
        ];
    }
}

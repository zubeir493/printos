<?php

namespace App\Filament\Resources\Partners\Pages;

use App\Filament\Resources\Partners\PartnerResource;
use App\Filament\Support\PanelAccess;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPartner extends EditRecord
{
    protected static string $resource = PartnerResource::class;

    public static function canAccess($record = null): bool
    {
        return PanelAccess::canManagePartners();
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->color('gray'),
        ];
    }
}

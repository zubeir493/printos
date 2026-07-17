<?php

namespace App\Filament\Resources\JobOrders\Pages;

use App\Filament\Resources\JobOrders\Actions\JobOrderActions;
use App\Filament\Resources\JobOrders\JobOrderResource;
use App\Filament\Support\PanelAccess;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditJobOrder extends EditRecord
{
    protected static string $resource = JobOrderResource::class;

    public static function canAccess($record = null): bool
    {
        return PanelAccess::canManageJobOrders();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Edit '.$this->getRecord()->job_order_number;
    }

    protected function getHeaderActions(): array
    {
        return [
            JobOrderActions::make(includeDelete: true),
        ];
    }
}

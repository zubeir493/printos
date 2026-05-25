<?php

namespace App\Filament\Resources\JobOrders\Pages;

use App\Filament\Resources\JobOrders\JobOrderResource;
use App\Filament\Support\PanelAccess;
use App\Filament\Widgets\JobOrdersStatsWidget;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJobOrders extends ListRecords
{
    protected static string $resource = JobOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn () => PanelAccess::canManageJobOrders()),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            JobOrdersStatsWidget::class,
        ];
    }
}

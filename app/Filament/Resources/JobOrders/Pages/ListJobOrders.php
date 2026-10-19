<?php

namespace App\Filament\Resources\JobOrders\Pages;

use App\Filament\Exports\JobOrderExporter;
use App\Filament\Imports\JobOrderImporter;
use App\Filament\Resources\JobOrders\JobOrderResource;
use App\Filament\Support\PanelAccess;
use App\Filament\Widgets\JobOrdersStatsWidget;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListJobOrders extends ListRecords
{
    protected static string $resource = JobOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn () => PanelAccess::canManageJobOrders()),
            ActionGroup::make([
                ImportAction::make()
                    ->importer(JobOrderImporter::class)->visible(fn (): bool => PanelAccess::canManageJobOrders()),
                ExportAction::make()
                    ->exporter(JobOrderExporter::class)->visible(fn (): bool => PanelAccess::canSeeMoneyValues()),
            ]),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            JobOrdersStatsWidget::class,
        ];
    }
}

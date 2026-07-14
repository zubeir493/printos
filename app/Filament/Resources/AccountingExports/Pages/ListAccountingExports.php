<?php

namespace App\Filament\Resources\AccountingExports\Pages;

use App\Filament\Resources\AccountingExports\AccountingExportResource;
use App\Models\AccountingExport;
use App\Models\AccountingIntegration;
use App\Services\Accounting\GenerateAccountingExport;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListAccountingExports extends ListRecords
{
    protected static string $resource = AccountingExportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')
                ->label('Generate export')
                ->icon('heroicon-o-arrow-down-tray')
                ->requiresConfirmation()
                ->action(function (): void {
                    $integration = AccountingIntegration::peachtreeDesktop();
                    $export = app(GenerateAccountingExport::class)->handle(
                        $integration,
                        now($integration->timezone),
                        auth()->user(),
                    );

                    Notification::make()
                        ->title(match ($export?->status) {
                            AccountingExport::STATUS_COMPLETED => 'Peachtree export generated',
                            AccountingExport::STATUS_FAILED => 'Peachtree export failed',
                            default => 'No journals to export',
                        })
                        ->body($export?->error_message)
                        ->color($export?->status === AccountingExport::STATUS_FAILED ? 'danger' : 'success')
                        ->send();
                }),
        ];
    }
}

<?php

namespace App\Filament\Resources\AccountingExports\Pages;

use App\Filament\Resources\AccountingExports\AccountingExportResource;
use App\Models\AccountingExport;
use App\Services\Accounting\GenerateAccountingExport;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewAccountingExport extends ViewRecord
{
    protected static string $resource = AccountingExportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download')
                ->label('Download Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(fn (): string => route('accounting-exports.download', $this->record))
                ->visible(fn (): bool => $this->record->status === AccountingExport::STATUS_COMPLETED),
            Action::make('retry')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->status === AccountingExport::STATUS_FAILED)
                ->action(function (): void {
                    $result = app(GenerateAccountingExport::class)->handle(
                        $this->record->integration,
                        $this->record->cutoff_at,
                        auth()->user(),
                        $this->record,
                    );
                    $this->record = $result ?? $this->record;

                    Notification::make()
                        ->title($result?->status === AccountingExport::STATUS_COMPLETED ? 'Export generated' : 'Export retry failed')
                        ->body($result?->error_message)
                        ->color($result?->status === AccountingExport::STATUS_COMPLETED ? 'success' : 'danger')
                        ->send();
                }),
        ];
    }
}

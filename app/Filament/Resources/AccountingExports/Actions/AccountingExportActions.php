<?php

namespace App\Filament\Resources\AccountingExports\Actions;

use App\Models\AccountingExport;
use App\Services\Accounting\GenerateAccountingExport;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class AccountingExportActions
{
    public static function make(): array
    {
        return [
            self::download(),
            self::retry(),
        ];
    }

    public static function download(): Action
    {
        return Action::make('download')
            ->label('Download')
            ->icon('heroicon-o-arrow-down-tray')
            ->openUrlInNewTab()
            ->iconButton()
            ->color('gray')
            ->url(fn(AccountingExport $record): string => route('accounting-exports.download', $record))
            ->visible(fn(AccountingExport $record): bool => $record->status === AccountingExport::STATUS_COMPLETED);
    }

    public static function retry(): Action
    {
        return Action::make('retry')
            ->icon('heroicon-o-arrow-path')
            ->iconButton()
            ->color('gray')
            ->requiresConfirmation()
            ->visible(fn(AccountingExport $record): bool => $record->status === AccountingExport::STATUS_FAILED)
            ->action(function (AccountingExport $record): void {
                $result = app(GenerateAccountingExport::class)->handle(
                    $record->integration,
                    $record->cutoff_at,
                    auth()->user(),
                    $record,
                );

                Notification::make()
                    ->title($result?->status === AccountingExport::STATUS_COMPLETED ? 'Export generated' : 'Export retry failed')
                    ->body($result?->error_message)
                    ->color($result?->status === AccountingExport::STATUS_COMPLETED ? 'success' : 'danger')
                    ->send();
            });
    }
}

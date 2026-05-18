<?php

namespace App\Filament\Resources\BankTransfers\Pages;

use App\Filament\Resources\BankTransfers\BankTransferResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditBankTransfer extends EditRecord
{
    protected static string $resource = BankTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('complete')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Complete Bank Transfer')
                ->modalDescription('This will update the bank balances. Are you sure?')
                ->visible(fn ($record) => $record->status === 'pending')
                ->action(function ($record): void {
                    try {
                        $record->complete(auth()->user());
                        $this->refreshFormData(['status', 'completed_by', 'completed_at']);

                        Notification::make()
                            ->title('Bank transfer completed')
                            ->success()
                            ->send();
                    } catch (\Throwable $exception) {
                        Notification::make()
                            ->title('Bank transfer could not be completed')
                            ->body($exception->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),
            Action::make('cancel')
                ->label('Cancel')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Cancel Bank Transfer')
                ->modalDescription('This will cancel the transfer without affecting balances. Are you sure?')
                ->visible(fn ($record) => $record->status === 'pending')
                ->action(function ($record): void {
                    try {
                        $record->cancel(auth()->user());
                        $this->refreshFormData(['status', 'completed_by', 'completed_at']);

                        Notification::make()
                            ->title('Bank transfer cancelled')
                            ->success()
                            ->send();
                    } catch (\Throwable $exception) {
                        Notification::make()
                            ->title('Bank transfer could not be cancelled')
                            ->body($exception->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),
            DeleteAction::make(),
        ];
    }
}

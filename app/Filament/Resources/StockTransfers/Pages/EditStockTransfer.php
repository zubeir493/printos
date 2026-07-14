<?php

namespace App\Filament\Resources\StockTransfers\Pages;

use App\Filament\Resources\StockTransfers\StockTransferResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditStockTransfer extends EditRecord
{
    protected static string $resource = StockTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                DeleteAction::make(),
                Action::make('complete')
                    ->label('Complete Transfer')
                    ->color('gray')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->post();
                        $this->record->refresh();
                        $this->refreshFormData(['status']);
                        Notification::make()
                            ->title('Transfer Completed Successfully')
                            ->success()
                            ->send();
                    }),
            ]),
        ];
    }
}

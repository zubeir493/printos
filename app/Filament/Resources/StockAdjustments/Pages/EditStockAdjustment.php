<?php

namespace App\Filament\Resources\StockAdjustments\Pages;

use App\Filament\Resources\StockAdjustments\StockAdjustmentResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditStockAdjustment extends EditRecord
{
    protected static string $resource = StockAdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                DeleteAction::make()
                    ->hidden(fn ($record) => $record->status === 'posted'),
                Action::make('post')
                    ->label('Post Adjustment')
                    ->color('gray')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === 'draft')
                    ->action(function ($record) {
                        $record->post();
                        $this->refreshFormData(['status', 'posted_at']);
                        Notification::make()
                            ->title('Adjustment Posted Successfully')
                            ->success()
                            ->send();
                    }),
            ]),
        ];
    }
}

<?php

namespace App\Filament\Resources\StockAdjustments\Pages;

use App\Filament\Resources\StockAdjustments\StockAdjustmentResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewStockAdjustment extends ViewRecord
{
    protected static string $resource = StockAdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                EditAction::make()
                    ->color('gray'),
                Action::make('post')
                    ->label('Post Adjustment')
                    ->color('gray')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->post();
                        Notification::make()
                            ->title('Adjustment Posted Successfully')
                            ->success()
                            ->send();
                    }),
            ])
                ->visible(fn ($record): bool => $record->status === 'draft'),
        ];
    }
}

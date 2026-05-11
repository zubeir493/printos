<?php

namespace App\Filament\Resources\Dispatches\Pages;

use App\Filament\Resources\Dispatches\DispatchResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewDispatch extends ViewRecord
{
    protected static string $resource = DispatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('complete_dispatch')
                ->label('Mark as Delivered')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn ($record) => $record->status === 'pending')
                ->requiresConfirmation()
                ->modalHeading('Confirm Delivery')
                ->modalDescription('Mark this dispatch as delivered? This confirms the items have been received by the customer.')
                ->action(function ($record) {
                    $record->update(['status' => 'completed']);
                    Notification::make()->title('Dispatch marked as delivered')->success()->send();
                }),

            Action::make('cancel_dispatch')
                ->label('Cancel Dispatch')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn ($record) => $record->status === 'pending')
                ->requiresConfirmation()
                ->modalHeading('Cancel Dispatch')
                ->modalDescription('Are you sure you want to cancel this dispatch?')
                ->action(function ($record) {
                    $record->update(['status' => 'cancelled']);
                    Notification::make()->title('Dispatch cancelled')->danger()->send();
                }),

            EditAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Resources\Dispatches\Actions;

use App\Models\Dispatch;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;

class DispatchActions
{
    public static function make(): ActionGroup
    {
        return ActionGroup::make([
            self::complete(),
            self::cancel(),
            self::edit(),
        ]);
    }

    public static function complete(): Action
    {
        return Action::make('complete_dispatch')
            ->label('Mark as Delivered')
            ->icon('heroicon-o-check-circle')
            ->color('gray')
            ->visible(fn (Dispatch $record): bool => $record->status === 'pending')
            ->requiresConfirmation()
            ->modalHeading('Confirm Delivery')
            ->modalDescription('Mark this dispatch as delivered? This confirms the items have been received by the customer.')
            ->action(function (Dispatch $record): void {
                $record->update(['status' => 'completed']);

                Notification::make()
                    ->title('Dispatch marked as delivered')
                    ->success()
                    ->send();
            });
    }

    public static function cancel(): Action
    {
        return Action::make('cancel_dispatch')
            ->label('Cancel Dispatch')
            ->icon('heroicon-o-x-circle')
            ->color('gray')
            ->visible(fn (Dispatch $record): bool => $record->status === 'pending')
            ->requiresConfirmation()
            ->modalHeading('Cancel Dispatch')
            ->modalDescription('Are you sure you want to cancel this dispatch?')
            ->action(function (Dispatch $record): void {
                $record->update(['status' => 'cancelled']);

                Notification::make()
                    ->title('Dispatch cancelled')
                    ->danger()
                    ->send();
            });
    }

    public static function edit(): EditAction
    {
        return EditAction::make()
            ->color('gray');
    }
}

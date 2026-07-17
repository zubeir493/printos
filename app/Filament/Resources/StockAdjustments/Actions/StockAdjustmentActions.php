<?php

namespace App\Filament\Resources\StockAdjustments\Actions;

use App\Models\StockAdjustment;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;

class StockAdjustmentActions
{
    public static function make(bool $includeDelete = false): ActionGroup
    {
        return ActionGroup::make(array_filter([
            self::edit(),
            self::post(),
            $includeDelete ? self::delete() : null,
        ]));
    }

    public static function edit(): EditAction
    {
        return EditAction::make()
            ->color('gray')
            ->visible(fn (StockAdjustment $record): bool => $record->status === 'draft');
    }

    public static function post(): Action
    {
        return Action::make('post')
            ->label('Post Adjustment')
            ->color('gray')
            ->icon('heroicon-o-check-circle')
            ->requiresConfirmation()
            ->visible(fn (StockAdjustment $record): bool => $record->status === 'draft')
            ->action(function (StockAdjustment $record): void {
                $record->post();

                Notification::make()
                    ->title('Adjustment Posted Successfully')
                    ->success()
                    ->send();
            });
    }

    public static function delete(): DeleteAction
    {
        return DeleteAction::make()
            ->visible(fn (StockAdjustment $record): bool => $record->status === 'draft');
    }
}

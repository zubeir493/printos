<?php

namespace App\Filament\Resources\ProductionReports\Actions;

use App\Models\ProductionReport;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;

class ProductionReportActions
{
    public static function make(bool $includeDelete = false): ActionGroup
    {
        return ActionGroup::make(array_filter([
            self::submit(),
            self::edit(),
            $includeDelete ? self::delete() : null,
        ]));
    }

    public static function submit(): Action
    {
        return Action::make('submit')
            ->label('Submit Report')
            ->icon('heroicon-o-check-circle')
            ->color('gray')
            ->requiresConfirmation()
            ->visible(fn (ProductionReport $record): bool => $record->status === 'draft')
            ->action(function (ProductionReport $record): void {
                $record->update(['status' => 'submitted']);

                Notification::make()
                    ->title('Production report submitted')
                    ->success()
                    ->send();
            });
    }

    public static function edit(): EditAction
    {
        return EditAction::make()
            ->color('gray')
            ->visible(fn (ProductionReport $record): bool => $record->status === 'draft');
    }

    public static function delete(): DeleteAction
    {
        return DeleteAction::make()
            ->visible(fn (ProductionReport $record): bool => $record->status === 'draft');
    }
}

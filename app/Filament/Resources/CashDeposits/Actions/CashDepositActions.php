<?php

namespace App\Filament\Resources\CashDeposits\Actions;

use App\Models\CashDeposit;
use App\Services\Accounting\PostCashDeposit;
use App\Services\Accounting\ReverseCashDeposit;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Throwable;

class CashDepositActions
{
    public static function make(bool $includeView = false, bool $includeEdit = false, bool $includeDelete = false): ActionGroup
    {
        return ActionGroup::make(array_filter([
            $includeView ? self::view() : null,
            $includeEdit ? self::edit() : null,
            self::post(),
            self::reverse(),
            $includeDelete ? self::delete() : null,
        ]));
    }

    public static function view(): ViewAction
    {
        return ViewAction::make()->color('gray');
    }

    public static function edit(): EditAction
    {
        return EditAction::make()
            ->color('gray')
            ->visible(fn (CashDeposit $record): bool => $record->status === CashDeposit::STATUS_PENDING);
    }

    public static function post(): Action
    {
        return Action::make('post')
            ->label('Post deposit')
            ->icon('heroicon-o-check-circle')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Post cash deposit')
            ->modalDescription(fn (CashDeposit $record): string => match ($record->deposit_type) {
                CashDeposit::TYPE_OTHER_INCOME,
                CashDeposit::TYPE_OTHER_SOURCES => 'This posts the bank debit and Other Income credit. The deposit cannot be edited afterward.',
                default => 'This posts the bank debit and Cash on Hand credit. The deposit cannot be edited afterward.',
            })
            ->visible(fn (CashDeposit $record): bool => $record->status === CashDeposit::STATUS_PENDING)
            ->action(function (CashDeposit $record): void {
                Gate::authorize('post', $record);

                try {
                    app(PostCashDeposit::class)->handle($record, auth()->user());
                    $record->refresh();

                    Notification::make()
                        ->title('Cash deposit posted')
                        ->success()
                        ->send();
                } catch (Throwable $exception) {
                    Notification::make()
                        ->title('Cash deposit could not be posted')
                        ->body($exception->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();
                }
            });
    }

    public static function reverse(): Action
    {
        return Action::make('reverse')
            ->label('Reverse deposit')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('danger')
            ->schema([
                Textarea::make('reason')
                    ->label('Reversal reason')
                    ->required()
                    ->maxLength(1000),
            ])
            ->modalHeading('Reverse cash deposit')
            ->modalDescription('This creates a reversing journal entry and reduces the bank balance. This action cannot be undone.')
            ->modalSubmitActionLabel('Reverse deposit')
            ->visible(fn (CashDeposit $record): bool => $record->status === CashDeposit::STATUS_POSTED)
            ->action(function (CashDeposit $record, array $data): void {
                Gate::authorize('reverse', $record);

                try {
                    app(ReverseCashDeposit::class)->handle($record, $data['reason'], auth()->user());
                    $record->refresh();

                    Notification::make()
                        ->title('Cash deposit reversed')
                        ->success()
                        ->send();
                } catch (Throwable $exception) {
                    Notification::make()
                        ->title('Cash deposit could not be reversed')
                        ->body($exception->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();
                }
            });
    }

    public static function delete(): DeleteAction
    {
        return DeleteAction::make()
            ->color('gray')
            ->visible(fn (CashDeposit $record): bool => $record->status === CashDeposit::STATUS_PENDING);
    }
}

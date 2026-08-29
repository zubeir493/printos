<?php

namespace App\Filament\Resources\CashDeposits\Actions;

use App\Models\Account;
use App\Models\CashDeposit;
use App\Services\Accounting\PostCashDeposit;
use App\Services\Accounting\ReconcileCashOnHand;
use App\Services\Accounting\ReverseCashDeposit;
use App\Support\Money;
use App\UserRole;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Throwable;

class CashDepositActions
{
    public static function make(
        bool $includeView = false,
        bool $includeEdit = false,
        bool $includeDelete = false,
        bool $includeReconcile = false,
    ): ActionGroup {
        return ActionGroup::make(array_filter([
            $includeView ? self::view() : null,
            $includeEdit ? self::edit() : null,
            self::post(),
            self::reverse(),
            $includeReconcile ? self::reconcile() : null,
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

    public static function reconcile(): Action
    {
        return Action::make('reconcile_cash')
            ->label('Reconcile cash balance')
            ->icon('heroicon-o-adjustments-horizontal')
            ->color('warning')
            ->schema([
                TextInput::make('amount')
                    ->label('Amount to restore')
                    ->numeric()
                    ->default(fn (): float => round(max(0, -CashDeposit::cashOnHandBalance()), 2))
                    ->maxValue(fn (): float => round(max(0, -CashDeposit::cashOnHandBalance()), 2))
                    ->minValue(0.01)
                    ->suffix(fn (): string => Money::suffix())
                    ->helperText(fn (): string => 'Current Cash on Hand deficit: '.Money::format(max(0, -CashDeposit::cashOnHandBalance()), 2))
                    ->required(),
                Select::make('offset_account_id')
                    ->label('Offset account (credit)')
                    ->options(fn (): array => Account::query()
                        ->whereIn('type', ['Equity', 'Liability', 'Revenue'])
                        ->whereNotIn('code', [Account::CODE_CASH, Account::CODE_BANK])
                        ->orderBy('code')
                        ->get()
                        ->mapWithKeys(fn (Account $account): array => [
                            $account->id => $account->code.' - '.$account->name,
                        ])
                        ->all())
                    ->default(fn (): ?int => Account::query()->where('code', '3000')->value('id'))
                    ->searchable()
                    ->preload()
                    ->helperText('Choose the account that explains the source of the missing cash, such as opening capital or a liability.')
                    ->required(),
                Textarea::make('reason')
                    ->label('Reconciliation reason')
                    ->placeholder('Explain which historical entries caused the deficit and why this correction is appropriate.')
                    ->required()
                    ->maxLength(1000),
            ])
            ->requiresConfirmation()
            ->modalHeading('Reconcile Cash on Hand')
            ->modalDescription('This posts a debit to Cash on Hand and a credit to the selected offset account. It does not change or delete historical journals.')
            ->modalSubmitActionLabel('Post reconciliation')
            // ->visible(fn (): bool => auth()->user()?->role === UserRole::Admin
            //     && CashDeposit::cashOnHandBalance() < 0)
            ->action(function (CashDeposit $record, array $data): void {
                Gate::authorize('reconcile', $record);

                try {
                    $journal = app(ReconcileCashOnHand::class)->handle(
                        amount: (float) $data['amount'],
                        offsetAccountId: (int) $data['offset_account_id'],
                        reason: $data['reason'],
                        user: auth()->user(),
                    );

                    Notification::make()
                        ->title('Cash balance reconciled')
                        ->body('Journal '.$journal->reference.' was posted. Cash on Hand is now '.Money::format(CashDeposit::cashOnHandBalance(), 2).'.')
                        ->success()
                        ->send();
                } catch (Throwable $exception) {
                    Notification::make()
                        ->title('Cash balance could not be reconciled')
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

<?php

namespace App\Filament\Resources\CashDeposits\Tables;

use App\Filament\Tables\Filters\DateRangeFilter;
use App\Models\CashDeposit;
use App\Services\Accounting\PostCashDeposit;
use App\Services\Accounting\ReverseCashDeposit;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Throwable;

class CashDepositsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('deposit_number')
                    ->label('Deposit')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('bank.name')
                    ->label('Bank')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('cashAccount.name')
                    ->label('Source')
                    ->toggleable(),
                TextColumn::make('amount')
                    ->formatStateUsing(fn ($state): string => Money::format($state))
                    ->weight('bold')
                    ->sortable(),
                TextColumn::make('deposit_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),
                TextColumn::make('reference')
                    ->label('Slip reference')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        CashDeposit::STATUS_PENDING => 'warning',
                        CashDeposit::STATUS_POSTED => 'success',
                        CashDeposit::STATUS_REVERSED => 'danger',
                    }),
            ])
            ->filters([
                DateRangeFilter::make('deposit_date_range', 'deposit_date', 'Deposit date'),
                SelectFilter::make('bank_id')
                    ->label('Bank')
                    ->relationship('bank', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->options([
                        CashDeposit::STATUS_PENDING => 'Pending',
                        CashDeposit::STATUS_POSTED => 'Posted',
                        CashDeposit::STATUS_REVERSED => 'Reversed',
                    ]),
            ])
            ->defaultSort('deposit_date', 'desc')
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()->color('gray'),
                    EditAction::make()
                        ->color('gray')
                        ->visible(fn (CashDeposit $record): bool => $record->status === CashDeposit::STATUS_PENDING),
                    Action::make('post')
                        ->label('Post deposit')
                        ->icon('heroicon-o-check-circle')
                        ->requiresConfirmation()
                        ->modalDescription('This posts the bank debit and cash credit. The deposit cannot be edited afterward.')
                        ->visible(fn (CashDeposit $record): bool => $record->status === CashDeposit::STATUS_PENDING)
                        ->action(fn (CashDeposit $record) => self::post($record)),
                    Action::make('reverse')
                        ->label('Reverse deposit')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('danger')
                        ->schema([
                            Textarea::make('reason')
                                ->label('Reversal reason')
                                ->required()
                                ->maxLength(1000),
                        ])
                        ->visible(fn (CashDeposit $record): bool => $record->status === CashDeposit::STATUS_POSTED)
                        ->action(fn (CashDeposit $record, array $data) => self::reverse($record, $data['reason'])),
                    DeleteAction::make()
                        ->color('gray')
                        ->visible(fn (CashDeposit $record): bool => $record->status === CashDeposit::STATUS_PENDING),
                ]),
            ])
            ->bulkActions([]);
    }

    private static function post(CashDeposit $deposit): void
    {
        Gate::authorize('post', $deposit);

        try {
            app(PostCashDeposit::class)->handle($deposit, auth()->user());
            Notification::make()->title('Cash deposit posted')->success()->send();
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Cash deposit could not be posted')
                ->body($exception->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }

    private static function reverse(CashDeposit $deposit, string $reason): void
    {
        Gate::authorize('reverse', $deposit);

        try {
            app(ReverseCashDeposit::class)->handle($deposit, $reason, auth()->user());
            Notification::make()->title('Cash deposit reversed')->success()->send();
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Cash deposit could not be reversed')
                ->body($exception->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }
}

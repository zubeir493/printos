<?php

namespace App\Filament\Resources\Banks\Tables;

use App\Filament\Resources\CashDeposits\CashDepositResource;
use App\Support\Money;
use Filament\Actions\Action as ActionsAction;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BanksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('bank_name')
                    ->label('Bank')
                    ->description(fn ($record) => $record->name)
                    ->searchable(),
                TextColumn::make('account_number')
                    ->label('Account')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Account number copied')
                    ->copyMessageDuration(1500),
                TextColumn::make('calculated_balance')
                    ->label('Balance')
                    ->formatStateUsing(fn ($state) => Money::format($state))
                    ->sortable()
                    ->color(fn ($record) => $record->calculated_balance < 0 ? 'danger' : 'success')
                    ->tooltip('Calculated from all payments and transfers'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'warning',
                        'closed' => 'danger',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Account Status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'closed' => 'Closed',
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    ActionsAction::make('deposit_cash')
                        ->label('Deposit cash')
                        ->icon('heroicon-o-banknotes')
                        ->url(fn ($record): string => CashDepositResource::getUrl('create', [
                            'bank_id' => $record->id,
                        ]))
                        ->visible(fn ($record): bool => $record->status === 'active'),
                    EditAction::make()
                        ->color('gray'),
                    ActionsAction::make('recalculate_balance')
                        ->label('Refresh')
                        ->icon('heroicon-o-arrow-path')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->action(fn ($record) => $record->updateBalance()),
                    DeleteAction::make()
                        ->color('gray'),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

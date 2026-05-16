<?php

namespace App\Filament\Resources\BankTransfers\Tables;

use App\Support\Money;
use Filament\Actions\Action as ActionsAction;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BankTransfersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transfer_number')
                    ->label('Transfer #')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Transfer number copied')
                    ->copyMessageDuration(1500),
                TextColumn::make('fromBank.name')
                    ->label('From Bank')
                    ->searchable(),
                TextColumn::make('toBank.name')
                    ->label('To Bank')
                    ->searchable(),
                TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state) => Money::format($state))
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('transfer_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Transfer Status')
                    ->options([
                        'pending' => 'Pending',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->defaultSort('transfer_date', 'desc')
            ->recordActions([
                ActionGroup::make([
                    ActionsAction::make('complete')
                        ->label('Approve')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Complete Bank Transfer')
                        ->modalDescription('This will update the bank balances. Are you sure?')
                        ->visible(fn ($record) => $record->status === 'pending')
                        ->action(function ($record) {
                            $record->complete(auth()->user());
                        }),
                    ActionsAction::make('cancel')
                        ->label('Cancel')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Cancel Bank Transfer')
                        ->modalDescription('This will cancel the transfer without affecting balances. Are you sure?')
                        ->visible(fn ($record) => $record->status === 'pending')
                        ->action(function ($record) {
                            $record->cancel(auth()->user());
                        }),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

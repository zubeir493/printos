<?php

namespace App\Filament\Resources\CashDeposits\Tables;

use App\Filament\Resources\CashDeposits\Actions\CashDepositActions;
use App\Filament\Tables\Filters\DateRangeFilter;
use App\Models\CashDeposit;
use App\Support\Money;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CashDepositsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('deposit_number')
                    ->label('Deposit')
                    ->description(fn (CashDeposit $record): string => $record->bank?->name ?? '-')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('deposit_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        CashDeposit::TYPE_OTHER_INCOME,
                        CashDeposit::TYPE_OTHER_SOURCES => 'Other sources',
                        default => 'Cash sales wallet',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        CashDeposit::TYPE_OTHER_INCOME,
                        CashDeposit::TYPE_OTHER_SOURCES => 'info',
                        default => 'primary',
                    }),
                TextColumn::make('source_account')
                    ->label('Source')
                    ->state(fn (CashDeposit $record): string => $record->cashAccount?->name
                        ?? $record->incomeAccount?->name
                        ?? '-'),
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
            ->recordActions([CashDepositActions::make(includeEdit: true, includeDelete: true)])
            ->bulkActions([]);
    }
}

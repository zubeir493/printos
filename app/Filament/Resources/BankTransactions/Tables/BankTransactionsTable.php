<?php

namespace App\Filament\Resources\BankTransactions\Tables;

use App\Filament\Exports\BankTransactionExporter;
use App\Filament\Tables\Filters\DateRangeFilter;
use App\Support\Money;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ExportAction;
use Filament\Actions\ExportBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BankTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transaction_number')
                    ->label('Transaction')
                    ->description(fn ($record) => $record->bank?->name)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('transaction_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->replace('_', ' ')->headline()->toString())
                    ->color(fn ($record): string => $record->direction === 'inbound' ? 'success' : 'danger'),
                TextColumn::make('counterparty')
                    ->label('Counterparty / Bank')
                    ->state(fn ($record) => $record->counterparty ?: $record->related_bank_name ?: '-')
                    ->searchable(['counterparty', 'related_bank_name']),
                TextColumn::make('balance_delta')
                    ->label('Balance Change')
                    ->formatStateUsing(fn ($state): string => ((float) $state >= 0 ? '+' : '').Money::format($state))
                    ->color(fn ($state): string => (float) $state >= 0 ? 'success' : 'danger')
                    ->weight('bold')
                    ->sortable(),
                TextColumn::make('transaction_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                DateRangeFilter::make('transaction_date_range', 'transaction_date', 'Transaction date'),
                SelectFilter::make('bank_id')
                    ->label('Bank')
                    ->relationship('bank', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('direction')
                    ->options([
                        'inbound' => 'Inbound',
                        'outbound' => 'Outbound',
                    ]),
                SelectFilter::make('source_type')
                    ->label('Source')
                    ->options([
                        'payment' => 'Payment',
                        'payment_void' => 'Payment Void',
                        'bank_transfer' => 'Transfer',
                    ]),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(BankTransactionExporter::class),
            ])
            ->recordActions([])
            ->defaultSort('transaction_date', 'desc')
            ->bulkActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(BankTransactionExporter::class),
                ]),
            ]);
    }
}

<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Enums\PaymentTransactionType;
use App\Filament\Exports\PaymentExporter;
use App\Filament\Tables\Filters\DateRangeFilter;
use App\Support\Money;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ExportBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('payment_number')
                    ->label('Payment')
                    ->description(fn ($record) => $record->partner?->name)
                    ->searchable(),
                TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(function ($state, $record) {
                        $prefix = $record->direction === 'inbound' ? '+' : '-';

                        return $prefix.Money::format($state);
                    })
                    ->description(fn ($record) => 'via '.ucfirst($record->method))
                    ->color(fn ($record) => $record->direction === 'inbound' ? 'success' : 'danger')
                    ->weight('bold')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('transaction_type')
                    ->badge()
                    ->label('Type')
                    ->formatStateUsing(function ($state) {
                        return PaymentTransactionType::tryFrom($state)?->label() ?? ucwords(str_replace('_', ' ', (string) $state));
                    })
                    ->color('primary'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn ($record) => $record->voided_at ? 'Voided' : 'Posted')
                    ->color(fn ($record) => $record->voided_at ? 'danger' : 'success'),
                TextColumn::make('payment_date')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                DateRangeFilter::make('payment_date_range', 'payment_date', 'Payment date'),
                SelectFilter::make('transaction_type')
                    ->label('Transaction Type')
                    ->options(PaymentTransactionType::options())
                    ->searchable(),
                TernaryFilter::make('posted_status')
                    ->label('Status')
                    ->placeholder('All')
                    ->trueLabel('Posted')
                    ->falseLabel('Voided')
                    ->queries(
                        true: fn ($query) => $query->whereNull('voided_at'),
                        false: fn ($query) => $query->whereNotNull('voided_at'),
                    ),
            ])
            ->defaultSort('payment_date', 'desc')
            ->actions([])
            ->bulkActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(PaymentExporter::class),
                ]),
            ]);
    }
}

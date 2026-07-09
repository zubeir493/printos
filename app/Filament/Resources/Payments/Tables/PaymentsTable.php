<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Enums\ExpenseTrackingType;
use App\Enums\PaymentTransactionType;
use App\Filament\Exports\PaymentExporter;
use App\Filament\Tables\Filters\DateRangeFilter;
use App\Support\Money;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ExportBulkAction;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('payment_number')
                    ->label('Payment')
                    ->description(fn($record) => $record->partner?->name)
                    ->searchable(),
                TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(function ($state, $record) {
                        $prefix = $record->direction === 'inbound' ? '+' : '-';

                        return $prefix . Money::format($state);
                    })
                    ->description(fn($record) => 'via ' . ucfirst($record->method))
                    ->color(fn($record) => $record->direction === 'inbound' ? 'success' : 'danger')
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
                    ->getStateUsing(fn($record) => $record->voided_at ? 'Voided' : 'Posted')
                    ->color(fn($record) => $record->voided_at ? 'danger' : 'success'),
                TextColumn::make('payment_date')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                DateRangeFilter::make('payment_date_range', 'payment_date', 'Payment date'),
                SelectFilter::make('transaction_type')
                    ->label('Transaction Type')
                    ->options(PaymentTransactionType::paymentFormOptions())
                    ->searchable(),
                SelectFilter::make('direction')
                    ->options([
                        'inbound' => 'Inbound',
                        'outbound' => 'Outbound',
                    ]),
                SelectFilter::make('method')
                    ->options([
                        'cash' => 'Cash',
                        'bank' => 'Bank Transfer',
                        'cheque' => 'Cheque',
                        'cpo' => 'CPO',
                    ]),
                Filter::make('posted_status')
                    ->label('Status')
                    ->schema([
                        Select::make('value')
                            ->options([
                                'posted' => 'Posted',
                                'voided' => 'Voided',
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'posted' => $query->whereNull('voided_at'),
                            'voided' => $query->whereNotNull('voided_at'),
                            default => $query,
                        };
                    }),
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

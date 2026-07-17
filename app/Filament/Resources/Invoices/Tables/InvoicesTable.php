<?php

namespace App\Filament\Resources\Invoices\Tables;

use App\Filament\Resources\Invoices\Actions\InvoiceActions;
use App\Filament\Tables\Filters\DateRangeFilter;
use App\Support\Money;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Invoice #')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => 'Generated for '.($record->partner?->name ?? 'Internal'))
                    ->weight('bold')
                    ->color('primary'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'draft' => 'gray',
                        'sent' => 'info',
                        'paid' => 'success',
                        'unpaid' => 'danger',
                        'partial' => 'warning',
                        'overdue' => 'danger',
                        'cancelled' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => ucfirst($state)),

                TextColumn::make('payment_progress')
                    ->label('Payment Progress')
                    ->getStateUsing(function ($record) {
                        $total = (float) $record->total_amount;
                        $paid = $total - (float) $record->balance_due;

                        return Money::format($paid).'/'.Money::format($total);
                    }),

                TextColumn::make('due_date')
                    ->label('Due Date')
                    ->date()
                    ->sortable()
                    ->color(fn ($record) => $record->isOverdue() ? 'danger' : null)
                    ->description(fn ($record) => $record->isOverdue() ? 'Overdue' : null),
            ])
            ->filters([
                DateRangeFilter::make('due_date_range', 'due_date', 'Due date'),

                SelectFilter::make('invoice_type')
                    ->label('Type')
                    ->options([
                        'sales' => 'Sales Invoices',
                        'purchase' => 'Purchase Invoices',
                        'service' => 'Service Invoices',
                        'receipt' => 'Receipts',
                    ]),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'sent' => 'Sent',
                        'paid' => 'Paid',
                        'partial' => 'Partial',
                        'overdue' => 'Overdue',
                        'cancelled' => 'Cancelled',
                    ]),

                Filter::make('overdue')
                    ->label('Overdue Only')
                    ->query(fn ($query) => $query->overdue())
                    ->toggle(),

                Filter::make('unpaid')
                    ->label('Unpaid Only')
                    ->query(fn ($query) => $query->where('status', '!=', 'paid'))
                    ->toggle(),
            ])
            ->defaultSort('due_date', 'desc')
            ->actions([
                InvoiceActions::make(),
            ])
            ->bulkActions([]);
    }
}

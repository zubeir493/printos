<?php

namespace App\Filament\Resources\Expenses\Tables;

use App\Enums\ExpenseTrackingType;
use App\Enums\PaymentTransactionType;
use App\Filament\Tables\Filters\DateRangeFilter;
use App\Models\Bid;
use App\Models\Employee;
use App\Models\ExpenseTrackingItem;
use App\Models\Payment;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExpensesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('payment_number')
                    ->label('Expense')
                    ->description(fn ($record) => $record->partner?->name ?? $record->reference)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('expenseAccount.name')
                    ->label('Type')
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('amount')
                    ->formatStateUsing(fn ($state): string => Money::format($state))
                    ->color('danger')
                    ->weight('bold')
                    ->sortable(),
                TextColumn::make('tracking')
                    ->label('Tracking')
                    ->state(fn ($record): ?string => $record->expenseTrackingLabel())
                    ->placeholder('-')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $query) use ($search): void {
                            $query
                                ->whereHas('expenseTrackingItem', fn (Builder $query) => $query
                                    ->where('name', 'like', "%{$search}%")
                                    ->orWhere('code', 'like', "%{$search}%"))
                                ->orWhereHas('expenseTrackingEmployee', fn (Builder $query) => $query
                                    ->where('first_name', 'like', "%{$search}%")
                                    ->orWhere('last_name', 'like', "%{$search}%"))
                                ->orWhereHas('expenseTrackingBid', fn (Builder $query) => $query
                                    ->where('bid_number', 'like', "%{$search}%")
                                    ->orWhere('title', 'like', "%{$search}%"));
                        });
                    }),
                TextColumn::make('payment_source')
                    ->label('Paid From')
                    ->badge()
                    ->state(fn (Payment $record): string => $record->paymentSourceLabel()),
                TextColumn::make('status')
                    ->badge()
                    ->state(fn ($record): string => $record->voided_at ? 'Voided' : 'Posted')
                    ->color(fn ($record): string => $record->voided_at ? 'danger' : 'success'),
                TextColumn::make('payment_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                DateRangeFilter::make('payment_date_range', 'payment_date', 'Payment date'),
                SelectFilter::make('expense_account_id')
                    ->label('Expense Category')
                    ->relationship('expenseAccount', 'name', fn (Builder $query) => $query->where('type', 'Expense'))
                    ->searchable()
                    ->preload(),
                SelectFilter::make('expense_tracking_type')
                    ->label('Tracking Type')
                    ->options(ExpenseTrackingType::options())
                    ->searchable(),
                SelectFilter::make('expense_tracking_item_id')
                    ->label('Tracking Item')
                    ->options(fn (): array => ExpenseTrackingItem::query()
                        ->orderBy('type')
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (ExpenseTrackingItem $item): array => [$item->id => "{$item->typeLabel()}: {$item->display_name}"])
                        ->all())
                    ->searchable(),
                SelectFilter::make('expense_tracking_employee_id')
                    ->label('Employee')
                    ->options(fn (): array => Employee::query()
                        ->orderBy('first_name')
                        ->orderBy('last_name')
                        ->get()
                        ->mapWithKeys(fn (Employee $employee): array => [$employee->id => $employee->full_name])
                        ->all())
                    ->searchable(),
                SelectFilter::make('expense_tracking_bid_id')
                    ->label('Bid')
                    ->options(fn (): array => Bid::query()
                        ->orderBy('bid_number')
                        ->get()
                        ->mapWithKeys(fn (Bid $bid): array => [$bid->id => $bid->title ? "{$bid->bid_number} - {$bid->title}" : $bid->bid_number])
                        ->all())
                    ->searchable(),
                SelectFilter::make('partner')
                    ->relationship('partner', 'name')
                    ->label('Vendor / Partner')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('payment_source')
                    ->label('Paid From')
                    ->options([
                        'petty_cash' => 'Petty Cash',
                        'cash' => 'Cash',
                        'bank' => 'Bank Transfer',
                        'cheque' => 'Cheque',
                        'cpo' => 'CPO',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'petty_cash' => $query->where(fn (Builder $query): Builder => $query
                                ->where('method', 'petty_cash')
                                ->orWhere('transaction_type', PaymentTransactionType::PETTY_CASH_EXPENSE->value)),
                            'cash' => $query
                                ->where('method', 'cash')
                                ->where('transaction_type', '!=', PaymentTransactionType::PETTY_CASH_EXPENSE->value),
                            'bank' => $query->whereIn('method', ['bank', 'bank_transfer']),
                            'cheque' => $query->whereIn('method', ['cheque', 'check']),
                            'cpo' => $query->where('method', 'cpo'),
                            default => $query,
                        };
                    }),
            ], layout: FiltersLayout::Modal)
            ->filtersFormColumns(3)
            ->filtersFormWidth(Width::FourExtraLarge)
            ->filtersTriggerAction(fn (Action $action): Action => $action->modalCancelAction(false))
            ->recordActions([])
            ->defaultSort('payment_date', 'desc');
    }
}

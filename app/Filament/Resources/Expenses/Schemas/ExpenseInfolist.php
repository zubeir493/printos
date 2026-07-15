<?php

namespace App\Filament\Resources\Expenses\Schemas;

use App\Enums\ExpenseTrackingType;
use App\Enums\PaymentTransactionType;
use App\Models\Payment;
use App\Support\Money;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ExpenseInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Expense')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('payment_number')
                            ->label('Payment')
                            ->weight('bold'),
                        TextEntry::make('payment_date')
                            ->label('Date')
                            ->date(),
                        TextEntry::make('amount')
                            ->formatStateUsing(fn ($state): string => Money::format($state))
                            ->weight('bold'),
                        TextEntry::make('transaction_type')
                            ->label('Type')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => PaymentTransactionType::tryFrom($state)?->label() ?? str($state)->headline()->toString()),
                        TextEntry::make('method')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => str($state)->headline()->toString()),
                        TextEntry::make('status')
                            ->badge()
                            ->state(fn (Payment $record): string => $record->voided_at ? 'Voided' : 'Posted')
                            ->color(fn (Payment $record): string => $record->voided_at ? 'danger' : 'success'),
                    ]),
                Section::make('Classification')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('expenseAccount.name')
                            ->label('Expense account')
                            ->placeholder('-'),
                        TextEntry::make('partner.name')
                            ->label('Vendor / Partner')
                            ->placeholder('-'),
                        TextEntry::make('reference')
                            ->placeholder('-'),
                        TextEntry::make('expense_tracking_type')
                            ->label('Tracking type')
                            ->formatStateUsing(fn (?string $state): string => ExpenseTrackingType::tryFrom((string) $state)?->label() ?? '-')
                            ->placeholder('-'),
                        TextEntry::make('tracking_label')
                            ->label('Tracking target')
                            ->state(fn (Payment $record): ?string => $record->expenseTrackingLabel())
                            ->placeholder('-')
                            ->columnSpan(2),
                    ]),
            ]);
    }
}

<?php

namespace App\Filament\Resources\BankTransactions\Schemas;

use App\Support\Money;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class BankTransactionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('transaction_number')
                    ->label('Transaction'),
                TextEntry::make('bank.name')
                    ->label('Bank'),
                TextEntry::make('source_type')
                    ->label('Source')
                    ->formatStateUsing(fn (string $state): string => str($state)->replace('_', ' ')->headline()->toString()),
                TextEntry::make('transaction_type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state): string => str($state)->replace('_', ' ')->headline()->toString()),
                TextEntry::make('balance_delta')
                    ->label('Balance Change')
                    ->formatStateUsing(fn ($state): string => ((float) $state >= 0 ? '+' : '').Money::format($state))
                    ->color(fn ($state): string => (float) $state >= 0 ? 'success' : 'danger'),
                TextEntry::make('counterparty')
                    ->label('Counterparty')
                    ->placeholder('-'),
                TextEntry::make('related_bank_name')
                    ->label('Related Bank')
                    ->placeholder('-'),
                TextEntry::make('transaction_date')
                    ->label('Date')
                    ->date(),
                TextEntry::make('reference')
                    ->placeholder('-'),
            ]);
    }
}

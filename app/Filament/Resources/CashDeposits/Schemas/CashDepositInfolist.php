<?php

namespace App\Filament\Resources\CashDeposits\Schemas;

use App\Support\Money;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CashDepositInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Deposit details')
                ->columns(2)
                ->schema([
                    TextEntry::make('deposit_number')->label('Deposit number'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('bank.name')->label('Destination bank'),
                    TextEntry::make('cashAccount.name')->label('Source cash account'),
                    TextEntry::make('amount')->formatStateUsing(fn ($state): string => Money::format($state)),
                    TextEntry::make('deposit_date')->date(),
                    TextEntry::make('reference')->label('Deposit slip reference'),
                    TextEntry::make('notes')->columnSpanFull(),
                    TextEntry::make('reversal_reason')
                        ->label('Reversal reason')
                        ->visible(fn ($record): bool => filled($record->reversal_reason))
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),
        ]);
    }
}

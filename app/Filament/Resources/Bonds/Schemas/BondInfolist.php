<?php

namespace App\Filament\Resources\Bonds\Schemas;

use App\Models\Bond;
use App\Support\Money;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class BondInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('type')
                    ->formatStateUsing(fn (string $state): string => Bond::typeOptions()[$state] ?? str($state)->headline()->toString())
                    ->badge(),
                TextEntry::make('bid.bid_number')
                    ->label('Bid'),
                TextEntry::make('bid.title')
                    ->label('Title'),
                TextEntry::make('issuingPartner.name')
                    ->label('Procuring Entity'),
                TextEntry::make('amount')
                    ->formatStateUsing(fn ($state): string => Money::format($state)),
                TextEntry::make('bank.name')
                    ->label('Bank')
                    ->state(fn (Bond $record): ?string => $record->cpo_bank_name ?: $record->bank?->name)
                    ->placeholder('-'),
                TextEntry::make('status')
                    ->formatStateUsing(fn (string $state): string => Bond::statusOptions()[$state] ?? str($state)->headline()->toString())
                    ->badge(),
                TextEntry::make('issue_date')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('recovery_date')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('reference')
                    ->placeholder('-'),
                TextEntry::make('notes')
                    ->placeholder('-')
                    ->columnSpanFull(),
            ]);
    }
}

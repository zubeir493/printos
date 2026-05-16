<?php

namespace App\Filament\Resources\StockMovements\Schemas;

use App\Support\Money;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class StockMovementInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('inventoryItem.name')
                    ->label('Inventory item'),
                TextEntry::make('warehouse.name')
                    ->label('Warehouse'),
                TextEntry::make('type'),
                TextEntry::make('reference_type')
                    ->placeholder('-'),
                TextEntry::make('reference_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('quantity')
                    ->numeric(),
                TextEntry::make('unit_cost')
                    ->formatStateUsing(fn ($state) => $state === null ? null : Money::format($state))
                    ->placeholder('-'),
                TextEntry::make('total_cost')
                    ->formatStateUsing(fn ($state) => $state === null ? null : Money::format($state))
                    ->placeholder('-'),
                TextEntry::make('movement_date')
                    ->dateTime('d M Y, h:i A'),
            ]);
    }
}

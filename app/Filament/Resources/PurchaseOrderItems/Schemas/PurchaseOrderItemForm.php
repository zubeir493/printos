<?php

namespace App\Filament\Resources\PurchaseOrderItems\Schemas;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PurchaseOrderItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('purchase_order_id')
                    ->relationship('purchaseOrder', 'id')
                    ->required(),
                Select::make('inventory_item_id')
                    ->relationship('inventoryItem', 'name')
                    ->required(),
                TextInput::make('quantity')
                    ->required()
                    ->numeric(),
                TextInput::make('unit_price')
                    ->required()
                    ->numeric()
                    ->suffix('Birr'),
                TextInput::make('total')
                    ->required()
                    ->numeric(),
                Placeholder::make('received_quantity')
                    ->label('Received Quantity')
                    ->content(fn ($record): string => number_format((float) ($record?->received_quantity ?? 0), 2)),
            ]);
    }
}

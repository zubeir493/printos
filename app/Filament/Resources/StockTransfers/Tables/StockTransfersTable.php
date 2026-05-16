<?php

namespace App\Filament\Resources\StockTransfers\Tables;

use App\Support\DateTimeDisplay;
use App\Support\StockTransferQuantity;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StockTransfersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transfer_number')
                    ->label('Transfer')
                    ->weight('bold')
                    ->description(fn ($record) => DateTimeDisplay::dateOrDateTime($record->transfer_date))
                    ->searchable(),
                TextColumn::make('fromWarehouse.name')
                    ->searchable(),
                TextColumn::make('toWarehouse.name')
                    ->searchable(),
                TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items'),
                TextColumn::make('items_summary')
                    ->label('Quantities')
                    ->state(fn ($record): string => $record->items
                        ->map(fn ($item): string => StockTransferQuantity::formattedQuantity($item->inventoryItem, $item->quantity).' '.$item->inventoryItem?->name)
                        ->join(', '))
                    ->wrap(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                    })
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->defaultSort('transfer_date', 'desc')
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

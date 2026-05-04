<?php

namespace App\Filament\Warehouse\Widgets;

use App\Models\InventoryBalance;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class PendingPickListTable extends BaseWidget
{
    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 1;

    protected static ?string $heading = 'Pending Pick-list';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                InventoryBalance::query()
                    ->with(['inventoryItem', 'warehouse'])
                    ->where('quantity_on_hand', '>', 0)
                    ->whereHas('inventoryItem', fn ($query) => $query->where('type', 'wip'))
                    ->orderByDesc('quantity_on_hand')
            )
            ->columns([
                Tables\Columns\TextColumn::make('inventoryItem.name')
                    ->label('WIP Item')
                    ->searchable(),
                Tables\Columns\TextColumn::make('inventoryItem.sku')
                    ->label('SKU')
                    ->searchable(),
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->searchable(),
                Tables\Columns\TextColumn::make('quantity_on_hand')
                    ->label('Available Qty')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2)),
                Tables\Columns\TextColumn::make('inventoryItem.unit')
                    ->label('Unit'),
            ]);
    }
}

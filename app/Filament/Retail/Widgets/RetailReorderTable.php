<?php

namespace App\Filament\Retail\Widgets;

use App\Models\InventoryBalance;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RetailReorderTable extends BaseWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

    protected static ?string $heading = 'Counter Stock Refill Queue';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                InventoryBalance::query()
                    ->with(['inventoryItem', 'warehouse'])
                    ->where('quantity_on_hand', '<', 10)
                    ->whereHas('inventoryItem', fn ($query) => $query->where('is_sellable', true))
                    ->orderBy('quantity_on_hand')
                    ->limit(8)
            )
            ->columns([
                Tables\Columns\TextColumn::make('inventoryItem.name')
                    ->label('Item')
                    ->searchable(),
                Tables\Columns\TextColumn::make('inventoryItem.sku')
                    ->label('SKU')
                    ->searchable(),
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Warehouse'),
                Tables\Columns\TextColumn::make('quantity_on_hand')
                    ->label('On Hand')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2))
                    ->color(fn ($state) => (float) $state <= 0 ? 'danger' : 'warning'),
                Tables\Columns\TextColumn::make('inventoryItem.unit')
                    ->label('Unit'),
            ])
            ->paginated(false);
    }
}

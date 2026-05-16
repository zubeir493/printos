<?php

namespace App\Filament\Warehouse\Widgets;

use App\Models\StockMovement;
use App\Support\DateTimeDisplay;
use App\Support\StockTransferQuantity;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentStockMovementsTable extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent Stock Movements';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                StockMovement::query()
                    ->with(['inventoryItem', 'warehouse'])
                    ->latest('movement_date')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('inventoryItem.name')
                    ->label('Item')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge(),
                Tables\Columns\TextColumn::make('quantity')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state, $record): string => StockTransferQuantity::formattedQuantity($record->inventoryItem, $state)),
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->searchable(),
                Tables\Columns\TextColumn::make('movement_date')
                    ->label('Moved At')
                    ->formatStateUsing(fn ($state) => DateTimeDisplay::dateOrDateTime($state)),
            ])
            ->paginated(false);
    }
}

<?php

namespace App\Filament\Sales\Widgets;

use App\Models\SalesOrder;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class SalesOrdersFocusTable extends BaseWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

    protected static ?string $heading = 'Orders Needing Sales Follow-up';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                SalesOrder::query()
                    ->with(['partner', 'warehouse'])
                    ->whereNotIn('status', ['completed', 'cancelled'])
                    ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('due_date')
                    ->limit(8)
            )
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Order')
                    ->searchable(),
                Tables\Columns\TextColumn::make('partner.name')
                    ->label('Customer')
                    ->searchable(),
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Warehouse'),
                Tables\Columns\TextColumn::make('due_date')
                    ->date()
                    ->color(fn ($state) => $state && $state->isPast() ? 'danger' : 'warning'),
                Tables\Columns\TextColumn::make('total')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2)),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
            ])
            ->paginated(false);
    }
}

<?php

namespace App\Filament\Warehouse\Widgets;

use App\Models\GoodsReceipt;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class ReceivingDiscrepancies extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Receiving Discrepancies (PO vs Actual)';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                GoodsReceipt::query()
                    ->with(['purchaseOrder', 'items.purchaseOrderItem'])
                    ->where('status', 'draft')
                    ->latest()
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('receipt_number')
                    ->label('Receipt #')
                    ->searchable(),
                Tables\Columns\TextColumn::make('purchaseOrder.order_number')
                    ->label('PO #'),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Lines')
                    ->state(fn (GoodsReceipt $record) => $record->items->count())
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color('warning'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime(),
            ]);
    }
}

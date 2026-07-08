<?php

namespace App\Filament\Resources\GoodsReceipts;

use App\Filament\Resources\GoodsReceipts\Pages\ListGoodsReceipts;
use App\Filament\Tables\Filters\DateRangeFilter;
use App\Models\GoodsReceipt;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;

class GoodsReceiptResource extends Resource
{
    protected static ?string $model = GoodsReceipt::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?int $navigationSort = 70;

    public static function getNavigationBadge(): ?string
    {
        // Count only draft goods receipts (exclude posted)
        $count = static::getModel()::where('status', 'draft')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('purchaseOrder.po_number')
                    ->label('Purchase Order')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('receipt_number')
                    ->label('Receipt')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Warehouse'),
                Tables\Columns\TextColumn::make('receipt_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),
            ])
            ->filters([
                DateRangeFilter::make('receipt_date_range', 'receipt_date', 'Receipt date'),
            ])
            ->defaultSort('receipt_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGoodsReceipts::route('/'),
        ];
    }
}

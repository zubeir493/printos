<?php

namespace App\Filament\Resources\GoodsReceipts;

use App\Filament\Resources\GoodsReceipts\Pages\ListGoodsReceipts;
use App\Filament\Support\PanelAccess;
use App\Filament\Tables\Filters\DateRangeFilter;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Support\Money;
use App\Support\WarehouseAccess;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GoodsReceiptResource extends Resource
{
    protected static ?string $model = GoodsReceipt::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?int $navigationSort = 70;

    public static function getNavigationBadge(): ?string
    {
        // Count only draft goods receipts (exclude posted)
        $count = static::getEloquentQuery()->where('status', 'draft')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return WarehouseAccess::scope(parent::getEloquentQuery(), 'goods_receipts.warehouse_id');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['supplier', 'purchaseOrder.partner']))
            ->columns([
                Tables\Columns\TextColumn::make('purchaseOrder.po_number')
                    ->label('Purchase Order')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('receipt_number')
                    ->label('Receipt')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('source')
                    ->state(fn (GoodsReceipt $record): string => $record->sourceLabel())
                    ->badge()
                    ->description(fn (GoodsReceipt $record): ?string => $record->supplier?->name ?? $record->purchaseOrder?->partner?->name),
                Tables\Columns\TextColumn::make('source_reference')
                    ->label('Reference')
                    ->searchable(),
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
            ->recordActions([
                Action::make('viewReceipt')->label('View receipt')->icon('heroicon-o-eye')
                    ->modalHeading(fn (GoodsReceipt $record): string => $record->receipt_number)
                    ->modalSubmitAction(false)->modalCancelActionLabel('Close')
                    ->schema([
                        RepeatableEntry::make('items')->label('Received items')
                            ->state(fn (GoodsReceipt $record): array => $record->items()->with(['inventoryItem', 'purchaseOrderItem.inventoryItem'])->get()
                                ->map(fn (GoodsReceiptItem $line): array => [
                                    'item_name' => ($line->inventoryItem ?? $line->purchaseOrderItem?->inventoryItem)?->name,
                                    'quantity_received' => $line->quantity_received,
                                    'unit_cost' => $line->unit_cost,
                                ])->all())
                            ->schema([
                                TextEntry::make('item_name')->label('Item'),
                                TextEntry::make('quantity_received')->label('Received'),
                                TextEntry::make('unit_cost')->label('Cost per base unit')
                                    ->money(fn (): string => Money::currencyCode())
                                    ->visible(fn (): bool => PanelAccess::canSeeMoneyValues()),
                            ])->columns(3),
                    ]),
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

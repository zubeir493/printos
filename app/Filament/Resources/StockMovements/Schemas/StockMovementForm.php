<?php

namespace App\Filament\Resources\StockMovements\Schemas;

use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Support\Money;
use App\Support\StockTransferQuantity;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class StockMovementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('inventory_item_id')
                    ->relationship('inventoryItem', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->reactive(),
                Select::make('warehouse_id')
                    ->relationship('warehouse', 'name')
                    ->default(fn () => Warehouse::where('is_default', true)->value('id'))
                    ->required()
                    ->searchable()
                    ->preload()
                    ->reactive(),
                TextInput::make('type')
                    ->required()
                    ->reactive()
                    ->helperText('Use a negative quantity for stock deductions and a positive quantity for receipts.'),
                TextInput::make('reference_type'),
                TextInput::make('reference_id')
                    ->numeric(),
                TextInput::make('quantity')
                    ->required()
                    ->numeric()
                    ->reactive()
                    ->formatStateUsing(fn ($state, ?StockMovement $record = null): float => StockTransferQuantity::displayQuantity($record?->inventoryItem, $state))
                    ->dehydrateStateUsing(fn ($state, Get $get): float => self::baseQuantityForItemId($get('inventory_item_id'), $state))
                    ->suffix(fn (Get $get): string => self::unitLabelForItemId($get('inventory_item_id')))
                    ->helperText(function (Get $get) {
                        $type = $get('type');
                        $itemId = $get('inventory_item_id');
                        $warehouseId = $get('warehouse_id');
                        $quantity = self::baseQuantityForItemId($itemId, $get('quantity'));

                        if (! $type || ! $itemId || ! $warehouseId || $quantity === 0.0) {
                            return null;
                        }

                        $item = InventoryItem::find($itemId);
                        $balance = InventoryBalance::where([
                            'inventory_item_id' => $itemId,
                            'warehouse_id' => $warehouseId,
                        ])->first();

                        $current = $balance ? (float) $balance->quantity_on_hand : 0.0;
                        $resulting = $current + $quantity;

                        if ($resulting < 0) {
                            return sprintf(
                                'This movement would make stock negative: %s -> %s.',
                                StockTransferQuantity::formattedQuantity($item, $current),
                                StockTransferQuantity::formattedQuantity($item, $resulting)
                            );
                        }

                        if (in_array($type, ['sale', 'consumption', 'transfer_out']) && $quantity > 0) {
                            return 'Outflow types normally use negative quantities; positive numbers will increase stock.';
                        }

                        if (in_array($type, ['purchase', 'transfer_in', 'production_output']) && $quantity < 0) {
                            return 'Inflow types normally use positive quantities; negative numbers will decrease stock.';
                        }

                        return null;
                    }),
                TextInput::make('unit_cost')
                    ->numeric()
                    ->suffix(fn (): string => Money::suffix()),
                TextInput::make('total_cost')
                    ->numeric()
                    ->suffix(fn (): string => Money::suffix()),
                DateTimePicker::make('movement_date')
                    ->seconds(false)
                    ->default(now())
                    ->required(),
            ]);
    }

    public static function baseQuantityForItemId(int|string|null $inventoryItemId, float|int|string|null $quantity): float
    {
        return StockTransferQuantity::baseQuantity(InventoryItem::find($inventoryItemId), $quantity);
    }

    public static function unitLabelForItemId(int|string|null $inventoryItemId): string
    {
        return StockTransferQuantity::unitLabel(InventoryItem::find($inventoryItemId));
    }
}

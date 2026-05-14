<?php

namespace App\Filament\Resources\StockMovements\Schemas;

use App\Models\InventoryBalance;
use App\Models\Warehouse;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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
                    ->helperText(function ($get) {
                        $type = $get('type');
                        $itemId = $get('inventory_item_id');
                        $warehouseId = $get('warehouse_id');
                        $quantity = (float) $get('quantity');

                        if (! $type || ! $itemId || ! $warehouseId || $quantity === 0.0) {
                            return null;
                        }

                        $balance = InventoryBalance::where([
                            'inventory_item_id' => $itemId,
                            'warehouse_id' => $warehouseId,
                        ])->first();

                        $current = $balance ? (float) $balance->quantity_on_hand : 0.0;
                        $resulting = $current + $quantity;

                        if ($resulting < 0) {
                            return sprintf(
                                'This movement would make stock negative: %s → %s.',
                                number_format($current, 2),
                                number_format($resulting, 2)
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
                    ->suffix(' Birr'),
                TextInput::make('total_cost')
                    ->numeric()
                    ->suffix(' Birr'),
                DateTimePicker::make('movement_date')
                    ->seconds(false)
                    ->default(now())
                    ->required(),
            ]);
    }
}

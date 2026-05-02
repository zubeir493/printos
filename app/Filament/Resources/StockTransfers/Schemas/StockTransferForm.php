<?php

namespace App\Filament\Resources\StockTransfers\Schemas;

use App\Models\InventoryBalance;
use App\Models\Warehouse;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;

class StockTransferForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        TextInput::make('transfer_number')
                            ->label('Transfer #')
                            ->default(function () {
                                $lastTransfer = \App\Models\StockTransfer::orderBy('id', 'desc')->first();
                                $lastNumber = 0;
                                if ($lastTransfer && preg_match('/ST-(\d+)/', $lastTransfer->transfer_number, $matches)) {
                                    $lastNumber = (int) $matches[1];
                                }
                                return 'ST-' . str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
                            })
                            ->readOnly()
                            ->columnSpan(2)
                            ->required(),
                        DatePicker::make('transfer_date')
                            ->columnSpan(2)
                            ->default(today())
                            ->required(),
                        TextInput::make('status')
                            ->default('Draft')
                            ->columnSpan(2)
                            ->readOnly()
                            ->dehydrated(false)
                            ->required(),
                        Select::make('from_warehouse_id')
                            ->relationship('fromWarehouse', 'name')
                            ->default(fn () => \App\Models\Warehouse::where('is_default', true)->value('id'))
                            ->required()
                            ->reactive()
                            ->columnSpan(3),
                        Select::make('to_warehouse_id')
                            ->relationship('toWarehouse', 'name')
                            ->required()
                            ->options(function (callable $get) {
                                $fromWarehouseId = $get('from_warehouse_id');
                                if (!$fromWarehouseId) {
                                    return Warehouse::pluck('name', 'id');
                                }
                                return Warehouse::where('id', '!=', $fromWarehouseId)->pluck('name', 'id');
                            })
                            ->columnSpan(3)
                            ->reactive(),
                    ])->columns(6)->columnSpan(4)
,
                Repeater::make('items')
                    ->relationship('items')
                    ->schema([
                        Select::make('inventory_item_id')
                            ->relationship('inventoryItem', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->reactive(),
                        TextInput::make('quantity')
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->step(0.01)
                            ->maxValue(function (callable $get, callable $set) {
                                $inventoryItemId = $get('inventory_item_id');
                                $fromWarehouseId = $get('../../from_warehouse_id');

                                if (!$inventoryItemId || !$fromWarehouseId) {
                                    return null;
                                }

                                $balance = InventoryBalance::where([
                                    'inventory_item_id' => $inventoryItemId,
                                    'warehouse_id' => $fromWarehouseId,
                                ])->first();

                                return $balance ? (float) $balance->quantity_on_hand : 0.0;
                            })
                            ->helperText(function (callable $get) {
                                $inventoryItemId = $get('inventory_item_id');
                                $fromWarehouseId = $get('../../from_warehouse_id');

                                if (!$inventoryItemId || !$fromWarehouseId) {
                                    return 'Select an item and from warehouse to see available quantity';
                                }

                                $balance = InventoryBalance::where([
                                    'inventory_item_id' => $inventoryItemId,
                                    'warehouse_id' => $fromWarehouseId,
                                ])->first();

                                $available = $balance ? (float) $balance->quantity_on_hand : 0.0;
                                return "Available: {$available} units";
                            })
                            ->reactive(),
                    ])
                    ->columnSpan(4)
                    ->required()
                    ->columns(2)
                    ->minItems(1),
            ])->columns(5);
    }
}

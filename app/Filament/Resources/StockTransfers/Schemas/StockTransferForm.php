<?php

namespace App\Filament\Resources\StockTransfers\Schemas;

use App\Models\InventoryItem;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use App\Support\StockTransferQuantity;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
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
                                $lastTransfer = StockTransfer::orderBy('id', 'desc')->first();
                                $lastNumber = 0;
                                if ($lastTransfer && preg_match('/ST-(\d+)/', $lastTransfer->transfer_number, $matches)) {
                                    $lastNumber = (int) $matches[1];
                                }

                                return 'ST-'.str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
                            })
                            ->readOnly()
                            ->columnSpan(2)
                            ->dehydrated(false),
                        DateTimePicker::make('transfer_date')
                            ->columnSpan(2)
                            ->seconds(false)
                            ->default(now())
                            ->required(),
                        TextInput::make('status')
                            ->default('Draft')
                            ->columnSpan(2)
                            ->readOnly()
                            ->dehydrated(false)
                            ->required(),
                        Select::make('from_warehouse_id')
                            ->relationship('fromWarehouse', 'name')
                            ->default(fn () => Warehouse::where('is_default', true)->value('id'))
                            ->required()
                            ->reactive()
                            ->columnSpan(3),
                        Select::make('to_warehouse_id')
                            ->relationship('toWarehouse', 'name')
                            ->required()
                            ->options(function (callable $get) {
                                $fromWarehouseId = $get('from_warehouse_id');
                                if (! $fromWarehouseId) {
                                    return Warehouse::pluck('name', 'id');
                                }

                                return Warehouse::where('id', '!=', $fromWarehouseId)->pluck('name', 'id');
                            })
                            ->columnSpan(3)
                            ->reactive(),
                    ])->columns(6)->columnSpan(4),
                Repeater::make('items')
                    ->relationship('items')
                    ->mutateRelationshipDataBeforeFillUsing(fn (array $data): array => StockTransferQuantity::convertRepeaterDataToDisplayUnits($data))
                    ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => StockTransferQuantity::convertRepeaterDataToBaseUnits($data))
                    ->mutateRelationshipDataBeforeSaveUsing(fn (array $data): array => StockTransferQuantity::convertRepeaterDataToBaseUnits($data))
                    ->schema([
                        Select::make('inventory_item_id')
                            ->relationship('inventoryItem', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $item = InventoryItem::find($state);

                                $set('unit_label', StockTransferQuantity::unitLabel($item));
                                $set('quantity', null);
                            })
                            ->afterStateHydrated(function ($state, callable $set) {
                                $item = InventoryItem::find($state);

                                $set('unit_label', StockTransferQuantity::unitLabel($item));
                            }),
                        TextInput::make('quantity')
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->step(0.01)
                            ->suffix(fn (callable $get): string => $get('unit_label') ?: 'unit')
                            ->maxValue(function (callable $get) {
                                $inventoryItemId = $get('inventory_item_id');
                                $fromWarehouseId = $get('../../from_warehouse_id');

                                return StockTransferQuantity::availableDisplayQuantity($inventoryItemId, $fromWarehouseId);
                            })
                            ->helperText(function (callable $get) {
                                $inventoryItemId = $get('inventory_item_id');
                                $fromWarehouseId = $get('../../from_warehouse_id');

                                if (! $inventoryItemId || ! $fromWarehouseId) {
                                    return 'Select an item and from warehouse to see available quantity';
                                }

                                $available = StockTransferQuantity::availableDisplayQuantity($inventoryItemId, $fromWarehouseId) ?? 0.0;
                                $unitLabel = $get('unit_label') ?: 'units';

                                return "Available: {$available} {$unitLabel}";
                            })
                            ->reactive(),
                        Hidden::make('unit_label')
                            ->default('unit')
                            ->dehydrated(false),
                    ])
                    ->columnSpan(4)
                    ->required()
                    ->columns(2)
                    ->minItems(1),
            ])->columns(5);
    }
}

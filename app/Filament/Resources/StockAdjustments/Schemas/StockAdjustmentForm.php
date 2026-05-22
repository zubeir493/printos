<?php

namespace App\Filament\Resources\StockAdjustments\Schemas;

use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\StockAdjustment;
use App\Models\Warehouse;
use App\Support\StockTransferQuantity;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section as ComponentsSection;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class StockAdjustmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                ComponentsSection::make()
                    ->schema([
                        TextInput::make('adjustment_number')
                            ->label('Adjustment Number')
                            ->default(function () {
                                $lastAdjustment = StockAdjustment::orderBy('id', 'desc')->first();
                                $lastNumber = 0;
                                if ($lastAdjustment && preg_match('/ADJ-(\d+)/', $lastAdjustment->adjustment_number, $matches)) {
                                    $lastNumber = (int) $matches[1];
                                }

                                return 'ADJ-'.str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
                            })
                            ->readOnly()
                            ->dehydrated(false),
                        Select::make('warehouse_id')
                            ->relationship('warehouse', 'name')
                            ->default(fn () => Warehouse::where('is_default', true)->value('id'))
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn ($set) => $set('items', []))
                            ->disabled(fn ($record) => $record?->status === 'posted'),
                        DatePicker::make('adjustment_date')
                            ->default(now())
                            ->required()
                            ->disabled(fn ($record) => $record?->status === 'posted'),
                        TextInput::make('reason')
                            ->disabled(fn ($record) => $record?->status === 'posted'),
                        Hidden::make('status')
                            ->default('draft'),
                    ])->columnSpanFull()->columns(4),

                Repeater::make('items')
                    ->relationship()
                    ->mutateRelationshipDataBeforeFillUsing(fn (array $data): array => self::convertRepeaterDataToDisplayUnits($data))
                    ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => self::convertRepeaterDataToBaseUnits($data))
                    ->mutateRelationshipDataBeforeSaveUsing(fn (array $data): array => self::convertRepeaterDataToBaseUnits($data))
                    ->table([
                        TableColumn::make('Inventory Item')->width('300px')->alignLeft(),
                        TableColumn::make('Adjustment')->alignLeft(),
                        TableColumn::make('Available')->alignLeft(),
                        TableColumn::make('Result')->alignLeft(),
                    ])
                    ->compact()
                    ->schema([
                        Select::make('inventory_item_id')
                            ->relationship('inventoryItem', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                                $warehouseId = $get('../../warehouse_id');

                                if ($state && $warehouseId) {
                                    $item = InventoryItem::find($state);
                                    $balance = InventoryBalance::where('inventory_item_id', $state)
                                        ->where('warehouse_id', $warehouseId)
                                        ->first();
                                    $system = StockTransferQuantity::displayQuantity($item, $balance?->quantity_on_hand ?? 0);
                                    $set('system_quantity', $system);

                                    $adj = (float) $get('adjustment_quantity');
                                    $set('new_quantity', $system + $adj);
                                    $set('difference', $adj);
                                }
                            })
                            ->disabled(fn ($get) => $get('../../status') === 'posted'),
                        TextInput::make('adjustment_quantity')
                            ->numeric()
                            ->required()
                            ->reactive()
                            ->suffix(fn (Get $get): string => self::unitPrefixForItemId($get('inventory_item_id')))
                            ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                                $system = (float) $get('system_quantity');
                                $adj = (float) $state;
                                $set('new_quantity', $system + $adj);
                                $set('difference', $adj);
                            })
                            ->helperText(fn (Get $get) => (float) $get('system_quantity') + (float) $get('adjustment_quantity') < 0
                                ? 'This adjustment will create negative stock. Please lower the negative quantity or correct the system quantity.'
                                : null)
                            ->disabled(fn ($get) => $get('../../status') === 'posted'),
                        TextInput::make('system_quantity')
                            ->numeric()
                            ->suffix(fn (Get $get): string => self::unitPrefixForItemId($get('inventory_item_id')))
                            ->disabled()
                            ->dehydrated()
                            ->required(),
                        TextInput::make('new_quantity')
                            ->numeric()
                            ->suffix(fn (Get $get): string => self::unitPrefixForItemId($get('inventory_item_id')))
                            ->disabled()
                            ->dehydrated()
                            ->required()
                            ->default(0)
                            ->helperText(fn (Get $get) => (float) $get('new_quantity') < 0
                                ? 'Resulting stock would be negative. This adjustment cannot be posted.'
                                : null),
                        Hidden::make('difference')
                            ->default(0)
                            ->required(),
                    ])
                    ->defaultItems(1)
                    ->minItems(1)
                    ->disabled(fn ($record) => $record?->status === 'posted')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function convertRepeaterDataToDisplayUnits(array $data): array
    {
        $item = InventoryItem::find($data['inventory_item_id'] ?? null);

        foreach (['system_quantity', 'adjustment_quantity', 'new_quantity', 'difference'] as $quantityField) {
            $data[$quantityField] = StockTransferQuantity::displayQuantity($item, $data[$quantityField] ?? 0);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function convertRepeaterDataToBaseUnits(array $data): array
    {
        $item = InventoryItem::find($data['inventory_item_id'] ?? null);

        foreach (['system_quantity', 'adjustment_quantity', 'new_quantity', 'difference'] as $quantityField) {
            $data[$quantityField] = StockTransferQuantity::baseQuantity($item, $data[$quantityField] ?? 0);
        }

        return $data;
    }

    public static function unitPrefixForItemId(int|string|null $inventoryItemId): string
    {
        return StockTransferQuantity::unitLabel(InventoryItem::find($inventoryItemId));
    }
}

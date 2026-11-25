<?php

namespace App\Filament\Resources\StockMovements\Tables;

use App\Filament\Exports\StockMovementExporter;
use App\Filament\Tables\Filters\DateRangeFilter;
use App\Models\GoodsReceipt;
use App\Models\MaintenanceStoreEntry;
use App\Support\DateTimeDisplay;
use App\Support\StockTransferQuantity;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ExportBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class StockMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('inventoryItem.name')
                    ->label('Item / Warehouse')
                    ->description(fn ($record) => $record->warehouse?->name)
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Str::headline($state))
                    ->color(fn (string $state): string => match ($state) {
                        'purchase', GoodsReceipt::DIRECT_MOVEMENT, 'transfer_in', 'material_return', 'production_output', MaintenanceStoreEntry::MOVEMENT_TOOL_RETURN => 'success',
                        'transfer_out', 'consumption', GoodsReceipt::REVERSAL_MOVEMENT, MaintenanceStoreEntry::TYPE_TOOL_CHECKOUT, MaintenanceStoreEntry::TYPE_SPARE_PART_ISSUE => 'danger',
                        'dispatch' => 'warning',
                        'adjustment' => 'primary',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('quantity')
                    ->formatStateUsing(fn ($state, $record): string => StockTransferQuantity::formattedQuantity($record->inventoryItem, $state))
                    ->sortable()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger')
                    ->weight('bold'),
                TextColumn::make('movement_date')
                    ->label('Moved At')
                    ->formatStateUsing(fn ($state) => DateTimeDisplay::dateOrDateTime($state))
                    ->sortable(),
            ])
            ->filters([
                DateRangeFilter::make('movement_date_range', 'movement_date', 'Movement date'),
                SelectFilter::make('type')
                    ->options([
                        'purchase' => 'Purchase',
                        GoodsReceipt::DIRECT_MOVEMENT => 'Direct receipt',
                        GoodsReceipt::REVERSAL_MOVEMENT => 'Direct receipt reversal',
                        'consumption' => 'Consumption',
                        'transfer_in' => 'Transfer In',
                        'transfer_out' => 'Transfer Out',
                        'dispatch' => 'Dispatch',
                        'adjustment' => 'Adjustment',
                        'material_return' => 'Material Return',
                        'production_output' => 'Production Output',
                        MaintenanceStoreEntry::TYPE_TOOL_CHECKOUT => 'Tool Checkout',
                        MaintenanceStoreEntry::MOVEMENT_TOOL_RETURN => 'Tool Return',
                        MaintenanceStoreEntry::TYPE_SPARE_PART_ISSUE => 'Spare Part Issue',
                    ]),
            ])
            ->recordActions([])
            ->defaultSort('movement_date', 'desc')
            ->bulkActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(StockMovementExporter::class),
                ]),
            ]);
    }
}

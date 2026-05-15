<?php

namespace App\Filament\Resources\StockMovements\Tables;

use App\Filament\Exports\StockMovementExporter;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ExportAction;
use Filament\Actions\ExportBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

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
                    ->color(fn (string $state): string => match ($state) {
                        'purchase', 'transfer_in', 'material_return', 'production_output' => 'success',
                        'transfer_out', 'consumption' => 'danger',
                        'dispatch' => 'warning',
                        'adjustment' => 'primary',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('quantity')
                    ->numeric()
                    ->sortable()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger')
                    ->weight('bold'),
                TextColumn::make('movement_date')
                    ->label('Moved At')
                    ->dateTime('d M Y, h:i A')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'purchase' => 'Purchase',
                        'consumption' => 'Consumption',
                        'transfer_in' => 'Transfer In',
                        'transfer_out' => 'Transfer Out',
                        'dispatch' => 'Dispatch',
                        'adjustment' => 'Adjustment',
                        'material_return' => 'Material Return',
                        'production_output' => 'Production Output',
                    ]),
            ])
            ->recordActions([
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(StockMovementExporter::class),
            ])
            ->defaultSort('movement_date', 'desc')
            ->bulkActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(StockMovementExporter::class),
                ]),
            ]);
    }
}

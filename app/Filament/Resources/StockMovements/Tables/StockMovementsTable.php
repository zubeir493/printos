<?php

namespace App\Filament\Resources\StockMovements\Tables;

use App\Filament\Exports\StockMovementExporter;
use App\Filament\Tables\Filters\DateRangeFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use App\Support\DateTimeDisplay;
use App\Support\StockTransferQuantity;
use Filament\Actions\BulkActionGroup;
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
                Filter::make('movement_date')
                    ->form([
                        DatePicker::make('moved_from'),
                        DatePicker::make('moved_until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['moved_from'] ?? null, fn (Builder $query, $start): Builder => $query->whereDate('movement_date', '>=', $start))
                        ->when($data['moved_until'] ?? null, fn (Builder $query, $end): Builder => $query->whereDate('movement_date', '<=', $end)))

                ,SelectFilter::make('type')
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
            ->defaultSort('movement_date', 'desc')
            ->bulkActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(StockMovementExporter::class),
                ]),
            ]);
    }
}

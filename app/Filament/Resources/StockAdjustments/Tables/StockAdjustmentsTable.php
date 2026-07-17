<?php

namespace App\Filament\Resources\StockAdjustments\Tables;

use App\Filament\Resources\StockAdjustments\Actions\StockAdjustmentActions;
use App\Filament\Tables\Filters\DateRangeFilter;
use App\Models\Warehouse;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StockAdjustmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('adjustment_number')
                    ->searchable(),
                TextColumn::make('warehouse.name')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'posted' => 'success',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('adjustment_date')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                DateRangeFilter::make('adjustment_date_range', 'adjustment_date', 'Adjustment date'),

                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'posted' => 'Posted',
                    ]),
                SelectFilter::make('warehouse_id')
                    ->label('Warehouse')
                    ->options(Warehouse::pluck('name', 'id')->toArray()),
            ])
            ->defaultSort('adjustment_date', 'desc')
            ->recordActions([
                StockAdjustmentActions::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

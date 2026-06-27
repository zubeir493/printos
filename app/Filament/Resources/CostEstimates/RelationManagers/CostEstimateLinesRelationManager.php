<?php

namespace App\Filament\Resources\CostEstimates\RelationManagers;

use App\Models\CostEstimateLine;
use App\Support\Money;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

class CostEstimateLinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Cost Breakdown';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->where('total', '>', 0)
                ->with('inventoryItem'))
            ->columns([
                TextColumn::make('label')
                    ->label('Item')
                    ->state(fn (CostEstimateLine $record): string => self::lineItemLabel($record))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->headline()->value())
                    ->sortable(),
                TextColumn::make('quantity')
                    ->label('Qty')
                    ->state(fn (CostEstimateLine $record): string => trim(Number::format((float) $record->quantity, maxPrecision: 4).' '.($record->unit ?? '')))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('unit_cost')
                    ->label('Unit Cost')
                    ->formatStateUsing(fn ($state): string => Money::format($state, 2))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn ($state): string => Money::format($state, 2))
                    ->summarize(Sum::make())
                    ->extraAttributes(['class' => 'fi-font-semibold fi-text-base'])
                    ->alignEnd()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->options(fn (): array => CostEstimateLine::query()
                        ->where('total', '>', 0)
                        ->distinct()
                        ->orderBy('category')
                        ->pluck('category', 'category')
                        ->map(fn (string $category): string => str($category)->headline()->value())
                        ->all()),
                SelectFilter::make('inventory_item_id')
                    ->label('Material')
                    ->relationship('inventoryItem', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->emptyStateHeading('No costed lines')
            ->searchable(true)
            ->defaultSort('sort');
    }

    private static function lineItemLabel(CostEstimateLine $record): string
    {
        if (! $record->inventoryItem) {
            return $record->label;
        }

        return "{$record->inventoryItem->name} ({$record->label})";
    }
}

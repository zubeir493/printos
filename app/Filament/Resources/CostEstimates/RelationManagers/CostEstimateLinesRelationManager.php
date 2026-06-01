<?php

namespace App\Filament\Resources\CostEstimates\RelationManagers;

use App\Support\Money;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CostEstimateLinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Cost Breakdown';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('category')->badge(),
                TextColumn::make('label')->searchable(),
                TextColumn::make('inventoryItem.name')->label('Material')->placeholder('-'),
                TextColumn::make('quantity')->numeric(4),
                TextColumn::make('unit')->placeholder('-'),
                TextColumn::make('unit_cost')->formatStateUsing(fn ($state): string => Money::format($state, 2)),
                TextColumn::make('total')->formatStateUsing(fn ($state): string => Money::format($state, 2)),
            ])
            ->defaultSort('sort');
    }
}

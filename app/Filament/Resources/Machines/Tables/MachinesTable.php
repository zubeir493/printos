<?php

namespace App\Filament\Resources\Machines\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MachinesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('code')
                    ->searchable(),
                TextColumn::make('operation_type')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => str($state ?? 'uncategorized')->replace('_', ' ')->headline()->value())
                    ->color('primary'),
                TextColumn::make('production_speed')
                    ->label('Speed')
                    ->state(fn ($record): string => number_format((float) $record->production_speed, 2).' units/hr'),
                TextColumn::make('hourly_cost')
                    ->label('Hourly')
                    ->money('ETB'),
                TextColumn::make('baseline_rounds_per_week')
                    ->label('Baseline/Week')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

<?php

namespace App\Filament\Resources\Machines\Tables;

use App\Support\Money;
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
                    ->state(fn ($record): string => number_format((float) $record->production_speed, 2).' '.str_replace('_per_hour', '/hr', $record->speed_unit ?? 'units_per_hour')),
                TextColumn::make('costing_configured')->label('Costing')->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Verified' : 'Needs setup')
                    ->color(fn (bool $state): string => $state ? 'success' : 'warning'),
                TextColumn::make('hourly_cost')
                    ->label('Hourly')
                    ->formatStateUsing(fn ($state): string => Money::format($state, 2)),
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

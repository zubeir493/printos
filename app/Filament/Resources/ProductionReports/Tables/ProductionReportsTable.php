<?php

namespace App\Filament\Resources\ProductionReports\Tables;

use App\Filament\Resources\ProductionReports\Actions\ProductionReportActions;
use App\Filament\Tables\Filters\DateRangeFilter;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductionReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('productionPlan.id')
                    ->label('Production Plan')
                    ->formatStateUsing(fn ($record) => "Plan: {$record->productionPlan->week_start->format('M d')} - {$record->productionPlan->week_end->format('M d')}")
                    ->searchable()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'submitted' => 'success',
                        default => 'gray',
                    }),
            ])
            ->filters([
                DateRangeFilter::make('created_date_range', 'created_at', 'Created date'),

                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'submitted' => 'Submitted',
                    ]),
            ])
            ->recordActions([
                ProductionReportActions::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([]),
            ]);
    }
}

<?php

namespace App\Filament\Resources\CostEstimates\Tables;

use App\Filament\Resources\CostEstimates\Actions\CostEstimateActions;
use App\Filament\Tables\Filters\DateRangeFilter;
use App\Support\Money;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CostEstimatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('estimate_number')
                    ->label('Estimate #')
                    ->searchable()
                    // ->description(fn(CostEstimate $record): string => $record->description)
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),
                TextColumn::make('job_type')
                    ->label('Service')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->headline()->value()),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'converted' => 'success',
                        'finalized' => 'info',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => str($state)->headline()->value()),
                TextColumn::make('unit_price')
                    ->formatStateUsing(fn ($state): string => Money::format($state, 4))
                    ->sortable(),
                TextColumn::make('total')
                    ->formatStateUsing(fn ($state): string => Money::format($state))
                    ->sortable(),
            ])
            ->filters([
                DateRangeFilter::make('created_date_range', 'created_at', 'Created date'),

                SelectFilter::make('job_type')
                    ->options([
                        'books' => 'Books',
                        'labels' => 'Labels',
                        'packages' => 'Packages',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'finalized' => 'Finalized',
                        'converted' => 'Converted',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                CostEstimateActions::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

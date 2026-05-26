<?php

namespace App\Filament\Resources\CostEstimates\Tables;

use App\Filament\Support\PanelAccess;
use App\Support\Money;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
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
                    ->sortable()
                    ->weight('bold')
                    ->color('primary')
                    ->description(fn ($record) => $record->partner?->name ?? 'No customer selected'),
                TextColumn::make('job_type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->headline()->value()),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'converted' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => str($state)->headline()->value()),
                TextColumn::make('total')
                    ->formatStateUsing(fn ($state): string => Money::format($state))
                    ->visible(fn () => PanelAccess::canSeeMoneyValues())
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('job_type')
                    ->options([
                        'books' => 'Books',
                        'packages' => 'Packages',
                        'labels' => 'Labels',
                        'vouchers' => 'Vouchers',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'converted' => 'Converted',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

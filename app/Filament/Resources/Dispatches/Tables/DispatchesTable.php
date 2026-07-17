<?php

namespace App\Filament\Resources\Dispatches\Tables;

use App\Filament\Resources\Dispatches\Actions\DispatchActions;
use App\Filament\Tables\Filters\DateRangeFilter;
use App\Models\JobOrder;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DispatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('jobOrder.job_order_number')
                    ->label('Job Order / Customer')
                    ->description(fn ($record) => $record->jobOrder->partner?->name)
                    ->weight('bold')
                    ->color('primary')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('delivery_date')
                    ->label('Date')
                    ->date('d M, Y')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->searchable(),
            ])
            ->filters([
                DateRangeFilter::make('delivery_date_range', 'delivery_date', 'Delivery date'),

                SelectFilter::make('job_order_id')
                    ->label('Job Order')
                    ->options(JobOrder::pluck('job_order_number', 'id')->toArray()),
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->defaultSort('delivery_date', 'desc')
            ->recordActions([
                DispatchActions::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

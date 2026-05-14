<?php

namespace App\Filament\Resources\Dispatches\Tables;

use App\Models\JobOrder;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
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
            ->recordActions([
                Action::make('complete_dispatch')
                    ->label('Mark as Delivered')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Confirm Delivery')
                    ->modalDescription('Mark this dispatch as delivered? This confirms the items have been received by the customer.')
                    ->action(function ($record): void {
                        $record->update(['status' => 'completed']);

                        Notification::make()
                            ->title('Dispatch marked as delivered')
                            ->success()
                            ->send();
                    }),
                Action::make('cancel_dispatch')
                    ->label('Cancel Dispatch')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Cancel Dispatch')
                    ->modalDescription('Are you sure you want to cancel this dispatch?')
                    ->action(function ($record): void {
                        $record->update(['status' => 'cancelled']);

                        Notification::make()
                            ->title('Dispatch cancelled')
                            ->danger()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

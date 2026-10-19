<?php

namespace App\Filament\Resources\TextFiles\Tables;

use App\Filament\Tables\Filters\DateRangeFilter;
use App\Models\JobOrder;
use App\Support\PrivateStorage;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class TextFilesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('jobOrder.name')
                    ->label('Task')
                    ->description(fn ($record) => $record->jobOrder?->job_order_number)
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('original_name')
                    ->label('File Name')
                    ->limit(50)
                    ->searchable(),
                TextColumn::make('uploader.name')
                    ->label('Uploaded By')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->dateTime()
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                DateRangeFilter::make('uploaded_date_range', 'created_at', 'Uploaded date'),

                SelectFilter::make('job_order_id')
                    ->label('Task')
                    ->options(fn () => JobOrder::query()
                        ->with('partner')
                        ->get()
                        ->mapWithKeys(fn ($task) => [
                            $task->id => "{$task->name} (#{$task->job_order_number})",
                        ])
                    )
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    Action::make('approve')
                        ->label('Approve')->icon('heroicon-m-check-badge')
                        ->authorize(fn ($record): bool => Gate::allows('approve', $record))
                        ->hidden(fn ($record): bool => $record->is_approved)
                        ->action(fn ($record) => $record->update(['is_approved' => true])),
                    Action::make('download')
                        ->label('Download')
                        ->icon('heroicon-m-arrow-down-tray')
                        ->url(fn ($record): ?string => PrivateStorage::downloadUrl($record->filename, now()->addMinutes(60)))
                        ->openUrlInNewTab(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

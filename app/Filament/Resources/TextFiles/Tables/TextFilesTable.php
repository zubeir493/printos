<?php

namespace App\Filament\Resources\TextFiles\Tables;

use App\Filament\Tables\Filters\DateRangeFilter;
use App\Models\JobOrderTask;
use App\Support\PrivateStorage;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TextFilesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('jobOrderTask.name')
                    ->label('Task')
                    ->description(fn ($record) => $record->jobOrderTask?->jobOrder?->job_order_number)
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

                SelectFilter::make('job_order_task_id')
                    ->label('Task')
                    ->options(fn () => JobOrderTask::query()
                        ->with('jobOrder')
                        ->get()
                        ->mapWithKeys(fn ($task) => [
                            $task->id => "{$task->name} (#{$task->jobOrder->job_order_number})",
                        ])
                    )
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ActionGroup::make([
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

<?php

namespace App\Filament\Resources\TextFiles\Tables;

use App\Support\PrivateStorage;
use Filament\Actions\Action;
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
                TextColumn::make('original_name')
                    ->label('File Name')
                    ->description(fn ($record) => $record->jobOrder?->job_order_number)
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('jobOrder.partner.name')
                    ->label('Customer')
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
                SelectFilter::make('job_order_id')
                    ->label('Job Order')
                    ->relationship(
                        'jobOrder',
                        'job_order_number',
                        fn ($query) => $query->where('production_mode', 'make_to_stock'),
                    )
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->url(fn ($record): ?string => PrivateStorage::downloadUrl($record->filename, now()->addMinutes(60)))
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

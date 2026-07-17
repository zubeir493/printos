<?php

namespace App\Filament\Resources\AccountingExports\Tables;

use App\Filament\Resources\AccountingExports\Actions\AccountingExportActions;
use App\Models\AccountingExport;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AccountingExportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->searchable()
            ->columns([
                TextColumn::make('cutoff_at')
                    ->label('Cutoff')
                    ->dateTime('H:i M j, Y')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->icon(fn(string $state): string => match ($state) {
                        AccountingExport::STATUS_COMPLETED => 'heroicon-o-check-circle',
                        AccountingExport::STATUS_FAILED => 'heroicon-o-exclamation-triangle',
                        default => 'heroicon-o-arrow-path',
                    })
                    ->color(fn(string $state): string => match ($state) {
                        AccountingExport::STATUS_COMPLETED => 'success',
                        AccountingExport::STATUS_FAILED => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('journal_count')
                    ->label('Journals')
                    ->numeric(),
                TextColumn::make('row_count')
                    ->label('Lines')
                    ->numeric(),
                TextColumn::make('generated_at')
                    ->label('Generated')
                    ->since()
                    ->placeholder('Not generated'),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    AccountingExport::STATUS_COMPLETED => 'Completed',
                    AccountingExport::STATUS_FAILED => 'Failed',
                    AccountingExport::STATUS_PROCESSING => 'Processing',
                ]),
            ])
            ->recordActions(
                AccountingExportActions::make(),
            )
            ->toolbarActions([]);
    }
}

<?php

namespace App\Filament\Resources\JournalEntries\Tables;

use App\Filament\Exports\JournalEntryExporter;
use App\Filament\Support\TableBadgeFormatter;
use App\Filament\Tables\Filters\DateRangeFilter;
use App\Support\Money;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class JournalEntriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')
                    ->searchable(),
                TextColumn::make('total_debit')
                    ->formatStateUsing(fn ($state) => Money::format($state))
                    ->label('Transferred Amount'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => TableBadgeFormatter::format($state))
                    ->icon(fn (string $state): string => match ($state) {
                        'draft' => 'heroicon-o-pencil',
                        'posted' => 'heroicon-o-check-circle',
                        'void' => 'heroicon-o-x-circle',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'posted' => 'success',
                        'void' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                DateRangeFilter::make('entry_date_range', 'entry_date', 'Entry date'),
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'posted' => 'Posted',
                        'void' => 'Void',
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(JournalEntryExporter::class),
                ]),
            ]);
    }
}

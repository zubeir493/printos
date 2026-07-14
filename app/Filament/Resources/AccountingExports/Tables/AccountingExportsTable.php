<?php

namespace App\Filament\Resources\AccountingExports\Tables;

use App\Models\AccountingExport;
use App\Services\Accounting\GenerateAccountingExport;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
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
                    ->icon(fn (string $state): string => match ($state) {
                        AccountingExport::STATUS_COMPLETED => 'heroicon-o-check-circle',
                        AccountingExport::STATUS_FAILED => 'heroicon-o-exclamation-triangle',
                        default => 'heroicon-o-arrow-path',
                    })
                    ->color(fn (string $state): string => match ($state) {
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
            ->recordActions([
                ActionGroup::make([
                    Action::make('download')
                        ->label('Download Excel')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('gray')
                        ->url(fn (AccountingExport $record): string => route('accounting-exports.download', $record))
                        ->visible(fn (AccountingExport $record): bool => $record->status === AccountingExport::STATUS_COMPLETED),
                    Action::make('retry')
                        ->icon('heroicon-o-arrow-path')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->visible(fn (AccountingExport $record): bool => $record->status === AccountingExport::STATUS_FAILED)
                        ->action(function (AccountingExport $record): void {
                            $result = app(GenerateAccountingExport::class)->handle(
                                $record->integration,
                                $record->cutoff_at,
                                auth()->user(),
                                $record,
                            );

                            Notification::make()
                                ->title($result?->status === AccountingExport::STATUS_COMPLETED ? 'Export generated' : 'Export retry failed')
                                ->body($result?->error_message)
                                ->color($result?->status === AccountingExport::STATUS_COMPLETED ? 'success' : 'danger')
                                ->send();
                        }),
                ]),
            ])
            ->toolbarActions([]);
    }
}

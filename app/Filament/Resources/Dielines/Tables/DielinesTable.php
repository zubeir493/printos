<?php

namespace App\Filament\Resources\Dielines\Tables;

use App\Models\Dieline;
use App\Services\Dielines\DielineExportService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DielinesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (Dieline $record): string => $record->jobOrderTask?->jobOrder?->job_order_number ?? 'Instant-ready dieline'),
                TextColumn::make('template.name')
                    ->label('Type')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('creator.name')
                    ->label('Created by')
                    ->placeholder('System')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->since()
                    ->sortable(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()->color('gray'),
                    self::downloadAction('svg', 'SVG'),
                    self::downloadAction('pdf', 'PDF'),
                    self::downloadAction('dxf', 'DXF'),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function downloadAction(string $format, string $label): Action
    {
        return Action::make("download_{$format}")
            ->label("Download {$label}")
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->action(fn (Dieline $record) => app(DielineExportService::class)->downloadDieline($record, $format));
    }
}

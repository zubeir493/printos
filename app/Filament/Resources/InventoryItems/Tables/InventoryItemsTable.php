<?php

namespace App\Filament\Resources\InventoryItems\Tables;

use App\Filament\Exports\InventoryItemExporter;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ExportBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InventoryItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Item')
                    ->description(fn ($record) => $record->sku)
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'raw_material' => 'gray',
                        'finished_good' => 'success',
                        'wip' => 'info',
                        'tools' => 'warning',
                        'spare_parts' => 'primary',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'raw_material' => 'Raw Material',
                        'finished_good' => 'Finished Good',
                        'wip' => 'WIP',
                        'tools' => 'Tools',
                        'spare_parts' => 'Spare Parts',
                        default => ucfirst(str_replace('_', ' ', $state)),
                    }),
                TextColumn::make('category')
                    ->label('Category')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'printing' => 'primary',
                        'design' => 'info',
                        'binding' => 'warning',
                        'finishing' => 'success',
                        'packaging' => 'secondary',
                        'stationery' => 'gray',
                        'marketing' => 'danger',
                        'other' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'printing' => 'Printing',
                        'design' => 'Design',
                        'binding' => 'Binding',
                        'finishing' => 'Finishing',
                        'packaging' => 'Packaging',
                        'stationery' => 'Stationery',
                        'marketing' => 'Marketing',
                        'other' => 'Other',
                        default => ucfirst($state ?? 'N/A'),
                    })
                    ->visible(fn ($record) => $record?->type === 'finished_good'),
                TextColumn::make('unit')
                    ->label('Unit')
                    ->badge()
                    ->color('gray'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'raw_material' => 'Raw Material',
                        'finished_good' => 'Finished Good',
                        'wip' => 'Produced (Undispatched)',
                        'tools' => 'Tools',
                        'spare_parts' => 'Spare Parts',
                    ]),
                SelectFilter::make('category')
                    ->label('Category')
                    ->options([
                        'printing' => 'Printing Services',
                        'design' => 'Design Services',
                        'binding' => 'Binding Services',
                        'finishing' => 'Finishing Services',
                        'packaging' => 'Packaging',
                        'stationery' => 'Stationery',
                        'marketing' => 'Marketing Materials',
                        'other' => 'Other',
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                ]),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(InventoryItemExporter::class),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ExportBulkAction::make()
                        ->exporter(InventoryItemExporter::class),
                ]),
            ]);
    }
}

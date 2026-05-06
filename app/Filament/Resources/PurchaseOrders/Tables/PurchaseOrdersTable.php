<?php

namespace App\Filament\Resources\PurchaseOrders\Tables;

use App\Filament\Exports\PurchaseOrderExporter;
use App\Filament\Support\PanelAccess;
use App\Models\Partner;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ExportBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PurchaseOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('po_number')
                    ->label('PO #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary')
                    ->description(fn ($record) => $record->partner?->name),
                TextColumn::make('order_date')
                    ->label('Date')
                    ->date()
                    ->sortable()
                    ->description(fn ($record) => $record->purchaseOrderItems()->count().' items'),
                TextColumn::make('subtotal')
                    ->label('Total')
                    ->suffix(' ETB')
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('balance')
                    ->label('Balance')
                    ->suffix(' ETB')
                    ->sortable()
                    ->color(fn ($record) => $record->balance > 0 ? 'warning' : 'success'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'approved' => 'info',
                        'received' => 'success',
                        'cancelled' => 'danger',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'Draft',
                        'approved' => 'Approved',
                        'received' => 'Received',
                        'cancelled' => 'Cancelled',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'approved' => 'Approved',
                        'received' => 'Received',
                        'cancelled' => 'Cancelled',
                    ]),
                SelectFilter::make('partner_id')
                    ->label('Supplier')
                    ->options(Partner::where('is_supplier', true)->pluck('name', 'id')->toArray()),
                Filter::make('unpaid')
                    ->label('Unpaid Only')
                    ->query(fn ($query) => $query->where('balance', '>', 0))
                    ->toggle(),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn () => PanelAccess::canManagePurchaseOrders()),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(PurchaseOrderExporter::class),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn () => PanelAccess::canManagePurchaseOrders()),
                    ExportBulkAction::make()
                        ->exporter(PurchaseOrderExporter::class)
                        ->visible(fn () => PanelAccess::canManagePurchaseOrders()),
                ]),
            ]);
    }
}

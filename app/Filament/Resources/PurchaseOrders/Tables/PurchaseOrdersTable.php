<?php

namespace App\Filament\Resources\PurchaseOrders\Tables;

use App\Filament\Exports\PurchaseOrderExporter;
use App\Filament\Resources\PurchaseOrders\Actions\PurchaseOrderActions;
use App\Filament\Support\PanelAccess;
use App\Filament\Tables\Filters\DateRangeFilter;
use App\Models\Partner;
use App\Support\Money;
use Filament\Actions\BulkActionGroup;
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
                TextColumn::make('payment_progress')
                    ->label('Payment Progress')
                    ->getStateUsing(function ($record) {
                        $total = $record->total ?? 0;
                        $paid = $record->paid_amount ?? 0;

                        if ($total == 0) {
                            return 'N/A';
                        }

                        $percentage = round(($paid / $total) * 100, 1);

                        return "{$percentage}% (".Money::format($paid).'/'.Money::format($total).')';
                    })
                    ->description(function ($record) {
                        $balance = $record->balance ?? 0;

                        return $balance > 0 ? 'Balance: '.Money::format($balance) : 'Paid in full';
                    })
                    ->color(function ($record) {
                        $total = $record->total ?? 0;
                        $paid = $record->paid_amount ?? 0;

                        if ($total == 0) {
                            return 'gray';
                        }

                        $percentage = ($paid / $total) * 100;

                        if ($percentage >= 100) {
                            return 'success';
                        }

                        if ($percentage >= 50) {
                            return 'warning';
                        }

                        return 'danger';
                    }),
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
                TextColumn::make('order_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                DateRangeFilter::make('order_date_range', 'order_date', 'Order date'),

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
                    ->options(fn (): array => Partner::query()
                        ->where('is_supplier', true)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all()),
                Filter::make('unpaid')
                    ->label('Unpaid Only')
                    ->query(fn ($query) => $query->where('balance', '>', 0))
                    ->toggle(),
            ])
            ->defaultSort('order_date', 'desc')
            ->recordActions([
                PurchaseOrderActions::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(PurchaseOrderExporter::class)
                        ->visible(fn () => PanelAccess::canManagePurchaseOrders()),
                ]),
            ]);
    }
}

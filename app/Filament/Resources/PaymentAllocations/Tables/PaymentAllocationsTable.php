<?php

namespace App\Filament\Resources\PaymentAllocations\Tables;

use App\Models\JobOrder;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ExportBulkAction;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PaymentAllocationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('payment.payment_number')
                    ->label('Payment #')
                    ->weight('bold')
                    ->color('primary')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->payment?->partner?->name),
                TextColumn::make('allocatable_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        JobOrder::class      => 'info',
                        SalesOrder::class    => 'success',
                        PurchaseOrder::class => 'warning',
                        default              => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        JobOrder::class      => 'Job Order',
                        SalesOrder::class    => 'Sales Order',
                        PurchaseOrder::class => 'Purchase Order',
                        default              => class_basename($state),
                    }),
                TextColumn::make('payment.method')
                    ->label('Method')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'cash'   => 'success',
                        'bank'   => 'info',
                        'cheque' => 'warning',
                        default  => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'bank'   => 'Bank Transfer',
                        'cheque' => 'Cheque',
                        default  => ucfirst($state ?? ''),
                    }),
                TextColumn::make('payment.payment_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),
                TextColumn::make('allocated_amount')
                    ->label('Amount')
                    ->suffix(' Birr')
                    ->weight('bold')
                    ->color(fn ($record) => $record->payment?->direction === 'inbound' ? 'success' : 'danger')
                    ->sortable()
                    ->summarize(Sum::make()->label('Total')->suffix(' Birr')),
            ])
            ->defaultSort('payment.payment_date', 'desc')
            ->filters([
                SelectFilter::make('allocatable_type')
                    ->label('Type')
                    ->options([
                        JobOrder::class      => 'Job Order',
                        SalesOrder::class    => 'Sales Order',
                        PurchaseOrder::class => 'Purchase Order',
                    ]),
                SelectFilter::make('method')
                    ->label('Method')
                    ->relationship('payment', 'method')
                    ->options([
                        'cash'   => 'Cash',
                        'bank'   => 'Bank Transfer',
                        'cheque' => 'Cheque',
                    ]),
            ])
            ->headerActions([
                \Filament\Actions\ExportAction::make()
                    ->exporter(\App\Filament\Exports\PaymentAllocationExporter::class),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ExportBulkAction::make()
                        ->exporter(\App\Filament\Exports\PaymentAllocationExporter::class),
                ]),
            ]);
    }
}

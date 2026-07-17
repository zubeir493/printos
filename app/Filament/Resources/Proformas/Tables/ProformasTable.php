<?php

namespace App\Filament\Resources\Proformas\Tables;

use App\Filament\Resources\Proformas\Actions\ProformaActions;
use App\Filament\Resources\Proformas\ProformaResource;
use App\Filament\Support\PanelAccess;
use App\Filament\Tables\Filters\DateRangeFilter;
use App\Models\Proforma;
use App\Support\Money;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProformasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('proforma_number')
                    ->label('Proforma #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary')
                    ->description(fn (Proforma $record): string => $record->partner?->name ?? 'Internal Job'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'sent' => 'info',
                        'approved' => 'success',
                        'job_order_created' => 'success',
                        'expired', 'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => str($state)->headline()->value()),
                TextColumn::make('expiry_date')
                    ->date()
                    ->sortable()
                    ->color(fn (Proforma $record): ?string => $record->expiry_date->isPast() && ! in_array($record->status, ['job_order_created', 'cancelled'], true) ? 'danger' : null),
                TextColumn::make('total')
                    ->formatStateUsing(fn ($state): string => Money::format($state))
                    ->visible(fn () => PanelAccess::canSeeMoneyValues())
                    ->sortable(),
            ])
            ->filters([
                DateRangeFilter::make('issue_date_range', 'issue_date', 'Issue date'),

                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'sent' => 'Sent',
                        'approved' => 'Approved',
                        'job_order_created' => 'Job Order Created',
                        'expired' => 'Expired',
                        'cancelled' => 'Cancelled',
                    ]),
                SelectFilter::make('job_type')
                    ->options([
                        'books' => 'Books',
                        'packages' => 'Packages',
                        'labels' => 'Labels',
                        'vouchers' => 'Vouchers',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (Proforma $record): string => ProformaResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ProformaActions::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

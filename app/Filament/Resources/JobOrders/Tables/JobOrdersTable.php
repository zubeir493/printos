<?php

namespace App\Filament\Resources\JobOrders\Tables;

use App\Filament\Exports\JobOrderExporter;
use App\Filament\Resources\JobOrders\Actions\JobOrderActions;
use App\Filament\Support\PanelAccess;
use App\Filament\Tables\Filters\DateRangeFilter;
use App\Support\Money;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ExportBulkAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class JobOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('job_order_number')
                    ->label('Job Order')
                    ->description(fn ($record) => $record->partner?->name ?? 'Internal Order')
                    ->weight('bold')
                    ->color('primary')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'draft' => 'warning',
                        'active' => 'info',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->weight(FontWeight::SemiBold)
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->description(fn ($record) => $record->completed_job_order_tasks_count.' / '.$record->job_order_tasks_count.' tasks done.'),
                TextColumn::make('submission_date')
                    ->label('Submission Date')
                    ->date()
                    ->sortable()
                    ->color(fn ($record) => $record->submission_date && $record->submission_date->isBefore(today()) && ! in_array($record->status, ['completed', 'cancelled']) ? 'danger' : null)
                    ->description(fn ($record) => $record->submission_date && $record->submission_date->isBefore(today()) && ! in_array($record->status, ['completed', 'cancelled']) ? 'Late' : null),
                TextColumn::make('total')
                    ->label('Payment Progress')
                    ->weight('bold')
                    ->formatStateUsing(fn ($record) => Money::format($record->paid_amount).'/'.Money::format($record->total))
                    ->description(fn ($record) => $record->balance > 0
                        ? 'Balance: '.Money::format($record->balance)
                        : 'Paid in full')
                    ->color(fn ($record): string => match (true) {
                        $record->balance <= 0 => 'success',
                        $record->paid_amount > 0 => 'warning',
                        default => 'danger',
                    })
                    ->weight(fn ($record): FontWeight => $record->balance <= 0
                        ? FontWeight::Bold
                        : FontWeight::SemiBold)
                    ->visible(fn ($record) => is_object($record)
                        && PanelAccess::canSeeMoneyValues()
                        && ($record->production_mode ?? null) !== 'make_to_stock')
                    ->sortable(),
            ])
            ->filters([
                DateRangeFilter::make('submission_date_range', 'submission_date', 'Submission date'),

                TernaryFilter::make('payment_status')
                    ->label('Payment Status')
                    ->placeholder('All')
                    ->trueLabel('Pending Payments')
                    ->falseLabel('Fully Paid')
                    ->queries(
                        true: fn ($query) => $query->pendingPayment(),
                        false: fn ($query) => $query->fullyPaid(),
                    ),
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'active' => 'Active',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ])
                    ->preload()
                    ->searchable(),
                Filter::make('late_jobs')
                    ->label('Late Job Orders')
                    ->query(fn ($query) => $query->late())
                    ->toggle(),
            ])
            ->defaultSort('submission_date', 'desc')
            ->recordActions([
                JobOrderActions::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(JobOrderExporter::class)
                        ->visible(fn () => PanelAccess::canManageJobOrders()),
                ]),
            ]);
    }
}

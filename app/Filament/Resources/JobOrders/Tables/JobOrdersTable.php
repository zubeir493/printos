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
                    ->description(fn ($record) => $record->isInternal() ? 'Internal job' : ($record->partner?->name ?? 'Client job'))
                    ->weight('bold')
                    ->color('primary')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')->label('Work Item')->searchable(),
                TextColumn::make('quantity')->numeric(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ((string) $state) {
                        'draft' => 'warning',
                        'design' => 'info', 'production' => 'primary',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->weight(FontWeight::SemiBold)
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->description(fn ($record) => $record->production_completed_at && (string) $record->status === 'production'
                        ? ($record->isInternal() ? 'Awaiting stock receipt' : 'Awaiting payment') : null),
                TextColumn::make('production_due_date')
                    ->label('Production due')
                    ->state(fn ($record) => $record->production_due_date ?? ($record->uses_production_ticket ? null : $record->submission_date))
                    ->date()
                    ->sortable()
                    ->color(fn ($record, $state) => $state?->isBefore(today()) && ! in_array((string) $record->status, ['completed', 'cancelled']) ? 'danger' : null),
                TextColumn::make('total')
                    ->label('Payment Progress')
                    ->weight('bold')
                    ->state(fn ($record) => $record->isInternal() ? null : Money::format($record->paid_amount).'/'.Money::format($record->total))
                    ->placeholder('—')
                    ->description(fn ($record) => $record->isInternal() ? null : ($record->balance > 0
                        ? 'Balance: '.Money::format($record->balance)
                        : 'Paid in full'))
                    ->color(fn ($record): string => match (true) {
                        $record->balance <= 0 => 'success',
                        $record->paid_amount > 0 => 'warning',
                        default => 'danger',
                    })
                    ->weight(fn ($record): FontWeight => $record->balance <= 0
                        ? FontWeight::Bold
                        : FontWeight::SemiBold)
                    ->visible(fn (): bool => PanelAccess::canSeeMoneyValues())
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('proforma_id')->relationship('proforma', 'proforma_number')->searchable()->preload(),
                SelectFilter::make('production_mode')->label('Job source')->options(['make_to_order' => 'Client job', 'make_to_stock' => 'Internal job']),
                DateRangeFilter::make('submission_date_range', 'submission_date', 'Submission date'),

                TernaryFilter::make('payment_status')
                    ->visible(fn (): bool => PanelAccess::canSeeMoneyValues())
                    ->label('Payment Status')
                    ->placeholder('All')
                    ->trueLabel('Pending Payments')
                    ->falseLabel('Fully Paid')
                    ->queries(
                        true: fn ($query) => $query->where('production_mode', 'make_to_order')->pendingPayment(),
                        false: fn ($query) => $query->where('production_mode', 'make_to_order')->fullyPaid(),
                    ),
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'design' => 'Design', 'production' => 'Production',
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

<?php

namespace App\Filament\Resources\JobOrders\Tables;

use App\Filament\Exports\JobOrderExporter;
use App\Filament\Support\PanelAccess;
use App\Services\InvoiceGeneratorService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ExportBulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
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
                    ->description(fn ($record) => $record->partner?->name)
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
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->description(fn ($record) => $record->jobOrderTasks()->where('status', 'completed')->count().' / '.$record->jobOrderTasks()->count().' tasks done.'),
                TextColumn::make('submission_date')
                    ->label('Submission Date')
                    ->date()
                    ->sortable()
                    ->color(fn ($record) => $record->submission_date && $record->submission_date->isBefore(today()) && ! in_array($record->status, ['completed', 'cancelled']) ? 'danger' : null)
                    ->description(fn ($record) => $record->submission_date && $record->submission_date->isBefore(today()) && ! in_array($record->status, ['completed', 'cancelled']) ? 'Late' : null),
                TextColumn::make('materials_completion')
                    ->label('Materials Issued')
                    ->state(fn ($record) => round($record->materialsCompletionPercentage(), 0).'%')
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        (int) $state >= 100 => 'success',
                        (int) $state >= 50 => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('total_price')
                    ->suffix(' birr')
                    ->visible(fn () => PanelAccess::canSeeMoneyValues())
                    ->sortable(),
                IconColumn::make('advance_paid')
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->paymentAllocations()->exists())
                    ->label('Adv. Paid'),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(JobOrderExporter::class),
            ])
            ->filters([
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
            ->actions([
                EditAction::make()
                    ->visible(fn () => PanelAccess::canManageJobOrders()),
            ])
            ->recordActions([
                Action::make('invoice')
                    ->label('Invoice')
                    ->icon('heroicon-o-document-text')
                    ->color('primary')
                    ->hidden(fn ($record) => $record->invoices()->exists() || ! PanelAccess::canSeeMoneyValues() || $record->balance <= 0)
                    ->action(function ($record) {
                        try {
                            $invoiceService = app(InvoiceGeneratorService::class);
                            $result = $invoiceService->generateFromJobOrder($record);

                            $actions = [
                                Action::make('download')
                                    ->label('Download')
                                    ->url($invoiceService->getInvoicePath($result['filename']))
                                    ->openUrlInNewTab(),
                            ];

                            // Only add email action if partner has email
                            if ($record->partner && $record->partner->email) {
                                $actions[] = Action::make('email')
                                    ->label('Email Invoice')
                                    ->icon('heroicon-o-envelope')
                                    ->action(function () use ($record, $result, $invoiceService) {
                                        $sent = $invoiceService->sendInvoiceEmail(
                                            $result,
                                            $record->partner->email
                                        );

                                        if ($sent) {
                                            // Update invoice record with email info
                                            $result['invoice']->update([
                                                'emailed_at' => now(),
                                                'email_recipient' => $record->partner->email,
                                            ]);

                                            Notification::make()
                                                ->title('Invoice Sent')
                                                ->body('Invoice emailed to '.$record->partner->email)
                                                ->success()
                                                ->send();
                                        } else {
                                            Notification::make()
                                                ->title('Email Failed')
                                                ->body('Failed to send invoice. Please check email configuration.')
                                                ->danger()
                                                ->send();
                                        }
                                    });
                            }

                            Notification::make()
                                ->title('Invoice Generated')
                                ->body('Invoice '.$result['invoice_data']['invoice_number'].' created successfully.')
                                ->success()
                                ->actions($actions)
                                ->send();

                            // Only add email action if partner has email
                            if ($record->partner && $record->partner->email) {
                                $actions[] = Action::make('email')
                                    ->label('Email Invoice')
                                    ->icon('heroicon-o-envelope')
                                    ->action(function () use ($record, $result, $invoiceService) {
                                        $sent = $invoiceService->sendInvoiceEmail(
                                            $result,
                                            $record->partner->email
                                        );

                                        if ($sent) {
                                            // Update invoice record with email info
                                            $result['invoice']->update([
                                                'emailed_at' => now(),
                                                'email_recipient' => $record->partner->email,
                                            ]);

                                            Notification::make()
                                                ->title('Invoice Sent')
                                                ->body('Invoice emailed to '.$record->partner->email)
                                                ->success()
                                                ->send();
                                        } else {
                                            Notification::make()
                                                ->title('Email Failed')
                                                ->body('Failed to send invoice. Please check email configuration.')
                                                ->danger()
                                                ->send();
                                        }
                                    });
                            }

                            Notification::make()
                                ->title('Invoice Generated')
                                ->body('Invoice '.$result['invoice_data']['invoice_number'].' created successfully.')
                                ->success()
                                ->actions($actions)
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Invoice Action Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn () => PanelAccess::canManageJobOrders()),
                    ExportBulkAction::make()
                        ->exporter(JobOrderExporter::class)
                        ->visible(fn () => PanelAccess::canManageJobOrders()),
                ]),
            ]);
    }
}

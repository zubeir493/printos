<?php

namespace App\Filament\Resources\SalesOrders\Tables;

use App\Filament\Exports\SalesOrderExporter;
use App\Filament\Support\PanelAccess;
use App\Models\Warehouse;
use App\Services\InvoiceGeneratorService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ExportBulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SalesOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->label('Sales Order')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->partner->name)
                    ->weight('bold')
                    ->color('primary'),
                TextColumn::make('paid_amount')
                    ->label('Payment Status')
                    ->state(fn ($record) => number_format($record->paid_amount, 2).'/'.number_format($record->total, 2).' Birr')
                    ->color(fn ($record) => $record->balance > 0 ? 'warning' : 'success')
                    ->description(fn ($record) => $record->paymentAllocations()->count() > 0
                        ? $record->paymentAllocations()->count().' payment(s)'
                        : 'No payments'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'completed' => 'success',
                        'void' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('order_date')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'completed' => 'Completed',
                        'void' => 'Void',
                    ]),
                SelectFilter::make('payment_mode')
                    ->label('Payment Type')
                    ->options([
                        'cash' => 'Cash',
                        'credit' => 'Credit',
                    ]),
                SelectFilter::make('warehouse_id')
                    ->label('Warehouse')
                    ->options(Warehouse::orderBy('name')->pluck('name', 'id')->all()),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn () => PanelAccess::canManageSalesOrders()),
                Action::make('invoice')
                    ->label('Invoice')
                    ->icon('heroicon-o-document-text')
                    ->color('primary')
                    ->hidden(fn ($record) => $record->invoices()->exists() || ! PanelAccess::canSeeMoneyValues() || $record->balance <= 0)
                    ->action(function ($record) {
                        try {
                            $invoiceService = app(InvoiceGeneratorService::class);
                            $result = $invoiceService->generateFromSalesOrder($record);

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
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Invoice Action Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('void')
                    ->label('Void')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => $record->status === 'completed')
                    ->requiresConfirmation()
                    ->modalHeading('Void Sales Order')
                    ->modalDescription('Are you sure you want to void this sales order? This action cannot be undone.')
                    ->modalSubmitActionLabel('Void Order')
                    ->action(function ($record) {
                        $record->update(['status' => 'void']);
                        Notification::make()
                            ->title('Sales Order Voided')
                            ->body($record->order_number.' has been voided.')
                            ->danger()
                            ->send();
                    }),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(SalesOrderExporter::class),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn () => PanelAccess::canManageSalesOrders()),
                    ExportBulkAction::make()
                        ->exporter(SalesOrderExporter::class)
                        ->visible(fn () => PanelAccess::canManageSalesOrders()),
                ]),
            ]);
    }
}

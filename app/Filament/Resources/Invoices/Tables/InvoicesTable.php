<?php

namespace App\Filament\Resources\Invoices\Tables;

use App\Services\InvoiceGeneratorService;
use Filament\Actions\Action as ActionsAction;
use Filament\Actions\BulkActionGroup as ActionsBulkActionGroup;
use Filament\Actions\DeleteBulkAction as ActionsDeleteBulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Invoice #')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => 'Generated for '.($record->partner?->name ?? 'Internal'))
                    ->weight('bold')
                    ->color('primary'),

                TextColumn::make('due_date')
                    ->label('Due Date')
                    ->date()
                    ->sortable()
                    ->since()
                    ->color(fn ($record) => $record->due_date->isPast() && $record->status !== 'paid' ? 'danger' : null)
                    ->description(fn ($record) => $record->due_date->isPast() && $record->status !== 'paid' ? 'Overdue' : null),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'draft' => 'gray',
                        'sent' => 'info',
                        'paid' => 'success',
                        'unpaid' => 'danger',
                        'partial' => 'warning',
                        'overdue' => 'danger',
                        'cancelled' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => ucfirst($state)),

                TextColumn::make('payment_progress')
                    ->label('Payment Progress')
                    ->getStateUsing(function ($record) {
                        $total = (float) $record->total_amount;
                        $paid = $total - (float) $record->balance_due;

                        return number_format($paid, 2).'/'.number_format($total, 2);
                    }),
            ])
            ->filters([
                SelectFilter::make('invoice_type')
                    ->label('Type')
                    ->options([
                        'sales' => 'Sales Invoices',
                        'purchase' => 'Purchase Invoices',
                        'service' => 'Service Invoices',
                        'receipt' => 'Receipts',
                    ]),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'sent' => 'Sent',
                        'paid' => 'Paid',
                        'partial' => 'Partial',
                        'overdue' => 'Overdue',
                        'cancelled' => 'Cancelled',
                    ]),

                Filter::make('overdue')
                    ->label('Overdue Only')
                    ->query(fn ($query) => $query->overdue())
                    ->toggle(),

                Filter::make('unpaid')
                    ->label('Unpaid Only')
                    ->query(fn ($query) => $query->where('status', '!=', 'paid'))
                    ->toggle(),
            ])
            ->actions([

                ActionsAction::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(function ($record) {
                        $invoiceService = app(InvoiceGeneratorService::class);

                        return $invoiceService->getInvoicePath($record->filename);
                    })
                    ->openUrlInNewTab(),

                ActionsAction::make('email')
                    ->label('Email Invoice')
                    ->icon('heroicon-o-envelope')
                    ->color('primary')
                    ->form([
                        TextInput::make('email')
                            ->label('Email Address')
                            ->email()
                            ->required()
                            ->default(fn ($record) => $record->partner?->email ?? $record->email_recipient)
                            ->placeholder('Enter email address'),
                        Textarea::make('message')
                            ->label('Message (Optional)')
                            ->placeholder('Add a custom message...')
                            ->rows(3),
                    ])
                    ->action(function (array $data, $record) {
                        try {
                            $invoiceService = app(InvoiceGeneratorService::class);
                            $sent = $invoiceService->sendInvoiceEmail(
                                [
                                    'filename' => $record->filename,
                                    'path' => $record->file_path,
                                    'invoice_data' => [
                                        'invoice_number' => $record->invoice_number,
                                        'invoice_date' => $record->invoice_date?->format('Y-m-d'),
                                        'partner' => $record->partner,
                                        'order' => (object) ['partner' => $record->partner],
                                        'due_date' => $record->due_date?->format('Y-m-d'),
                                        'total_amount' => $record->total_amount,
                                        'balance_due' => $record->balance_due,
                                        'message' => $data['message'] ?? null,
                                        'company_info' => config('invoice.company', [
                                            'name' => config('app.name', 'PrintOS'),
                                        ]),
                                    ],
                                ],
                                $data['email']
                            );

                            if ($sent) {
                                $record->update([
                                    'emailed_at' => now(),
                                    'email_recipient' => $data['email'],
                                ]);
                                Notification::make()
                                    ->title('Invoice Sent')
                                    ->body('Invoice sent to '.$data['email'])
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Email Failed')
                                    ->body('Failed to send invoice')
                                    ->danger()
                                    ->send();
                            }
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Email Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                ActionsBulkActionGroup::make([
                    ActionsDeleteBulkAction::make(),
                ]),
            ]);
    }
}

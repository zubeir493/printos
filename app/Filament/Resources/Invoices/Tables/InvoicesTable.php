<?php

namespace App\Filament\Resources\Invoices\Tables;

use App\Filament\Tables\Filters\DateRangeFilter;
use App\Services\InvoiceGeneratorService;
use App\Support\Money;
use Filament\Actions\Action as ActionsAction;
use Filament\Actions\ActionGroup;
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

                        return Money::format($paid).'/'.Money::format($total);
                    }),

                TextColumn::make('due_date')
                    ->label('Due Date')
                    ->date()
                    ->sortable()
                    ->color(fn ($record) => $record->isOverdue() ? 'danger' : null)
                    ->description(fn ($record) => $record->isOverdue() ? 'Overdue' : null),
            ])
            ->filters([
                DateRangeFilter::make('due_date_range', 'due_date', 'Due date'),

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
            ->defaultSort('due_date', 'desc')
            ->actions([
                ActionGroup::make([
                    ActionsAction::make('mark_sent')
                        ->label('Mark Sent')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('gray')
                        ->visible(fn ($record): bool => in_array($record->status, ['draft', 'unpaid'], true))
                        ->action(function ($record): void {
                            $record->update(['status' => 'sent']);

                            Notification::make()->title('Invoice marked as sent')->success()->send();
                        }),

                    ActionsAction::make('cancel_invoice')
                        ->label('Cancel Invoice')
                        ->icon('heroicon-o-x-circle')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->visible(fn ($record): bool => in_array($record->status, ['draft', 'sent', 'unpaid', 'partial', 'overdue'], true))
                        ->action(function ($record): void {
                            $record->update(['status' => 'cancelled']);

                            Notification::make()->title('Invoice cancelled')->success()->send();
                        }),

                    ActionsAction::make('download')
                        ->label('Download')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('gray')
                        ->url(function ($record) {
                            $invoiceService = app(InvoiceGeneratorService::class);

                            return $invoiceService->getInvoiceDownloadUrl($record->file_path, $record->filename);
                        })
                        ->openUrlInNewTab(),

                    ActionsAction::make('email')
                        ->label('Email Invoice')
                        ->icon('heroicon-o-envelope')
                        ->color('gray')
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
                                                'name' => config('app.name', 'Packledge'),
                                            ]),
                                        ],
                                    ],
                                    $data['email']
                                );

                                if ($sent) {
                                    $updates = [
                                        'emailed_at' => now(),
                                        'email_recipient' => $data['email'],
                                    ];

                                    if (! in_array($record->status, ['paid', 'cancelled'], true)) {
                                        $updates['status'] = 'sent';
                                    }

                                    $record->update($updates);
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
                ]),
            ])
            ->bulkActions([]);
    }
}

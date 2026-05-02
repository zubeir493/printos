<?php

namespace App\Filament\Resources\Invoices\Tables;

use Filament\Actions\Action as ActionsAction;
use Filament\Actions\BulkActionGroup as ActionsBulkActionGroup;
use Filament\Actions\DeleteBulkAction as ActionsDeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;

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
                    ->description(fn ($record) => 'Generated for ' . $record->partner->name)
                    ->weight('bold')
                    ->color('primary'),
                
                TextColumn::make('invoice_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'sales' => 'success',
                        'purchase' => 'warning', 
                        'service' => 'info',
                        'receipt' => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'sales' => 'Sales',
                        'purchase' => 'Purchase',
                        'service' => 'Service',
                        'receipt' => 'Receipt',
                    }),
                
                TextColumn::make('due_date')
                    ->label('Due Date')
                    ->date()
                    ->sortable()
                    ->since()
                    ->color(fn($record) => $record->due_date->isPast() && $record->status !== 'paid' ? 'danger' : null)
                    ->description(fn($record) => $record->due_date->isPast() && $record->status !== 'paid' ? 'Overdue' : null),
                
                BadgeColumn::make('status')
                    ->label('Status')
                    ->color(fn($state) => match ($state) {
                        'draft' => 'gray',
                        'sent' => 'info',
                        'paid' => 'success',
                        'overdue' => 'danger',
                        'cancelled' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn($state) => ucfirst($state)),
                
                TextColumn::make('payment_progress')
                    ->label('Payment Progress')
                    ->getStateUsing(function ($record) {
                        $total = $record->total_amount;
                        $paid = $total - $record->balance_due;
                        $percentage = $total > 0 ? round(($paid / $total) * 100, 1) : 0;
                        
                        return "{$paid} / {$total} Birr ({$percentage}%)";
                    })
                    ->description(function ($record) {
                        return $record->balance_due > 0 ? 'Balance: ' . $record->balance_due . ' Birr' : 'Fully Paid';
                    })
                    ->color(function ($record) {
                        return $record->balance_due > 0 ? 'warning' : 'success';
                    })
                    ->sortable()
                    ->alignEnd(),
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
                        'overdue' => 'Overdue',
                        'cancelled' => 'Cancelled',
                    ]),
                
                Filter::make('overdue')
                    ->label('Overdue Only')
                    ->query(fn($query) => $query->overdue())
                    ->toggle(),
                
                Filter::make('unpaid')
                    ->label('Unpaid Only')
                    ->query(fn($query) => $query->where('balance_due', '>', 0))
                    ->toggle(),
            ])
            ->actions([
                ActionsAction::make('edit')
                    ->label('Edit')
                    ->url(fn ($record): string => \App\Filament\Resources\Invoices\InvoiceResource::getUrl('edit', ['record' => $record]))
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary'),
                    
                ActionsAction::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(function ($record) {
                        $invoiceService = app(\App\Services\InvoiceGeneratorService::class);
                        return $invoiceService->getInvoicePath($record->filename);
                    })
                    ->openUrlInNewTab(),
                
                ActionsAction::make('resend_email')
                    ->label('Resend Email')
                    ->icon('heroicon-o-envelope')
                    ->color('success')
                    ->visible(fn($record) => $record->email_recipient && $record->status !== 'cancelled')
                    ->action(function ($record) {
                        try {
                            $invoiceService = app(\App\Services\InvoiceGeneratorService::class);
                            $sent = $invoiceService->sendInvoiceEmail(
                                [
                                    'filename' => $record->filename,
                                    'path' => $record->file_path,
                                    'invoice_data' => [
                                        'invoice_number' => $record->invoice_number,
                                        'partner' => $record->partner,
                                        'total' => $record->total_amount,
                                        'due_date' => $record->due_date,
                                    ]
                                ],
                                $record->email_recipient
                            );
                            
                            if ($sent) {
                                $record->update(['emailed_at' => now()]);
                                \Filament\Notifications\Notification::make()
                                    ->title('Email Resent')
                                    ->body('Invoice resent to ' . $record->email_recipient)
                                    ->success()
                                    ->send();
                            } else {
                                \Filament\Notifications\Notification::make()
                                    ->title('Email Failed')
                                    ->body('Failed to resend invoice')
                                    ->danger()
                                    ->send();
                            }
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Email Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                
                ActionsAction::make('email')
                    ->label('Email Invoice')
                    ->icon('heroicon-o-envelope')
                    ->color('primary')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('email')
                            ->label('Email Address')
                            ->email()
                            ->required()
                            ->default(fn($record) => $record->partner?->email ?? $record->email_recipient)
                            ->placeholder('Enter email address'),
                        \Filament\Forms\Components\Textarea::make('message')
                            ->label('Message (Optional)')
                            ->placeholder('Add a custom message...')
                            ->rows(3),
                    ])
                    ->action(function (array $data, $record) {
                        try {
                            $invoiceService = app(\App\Services\InvoiceGeneratorService::class);
                            $sent = $invoiceService->sendInvoiceEmail(
                                [
                                    'filename' => $record->filename,
                                    'path' => $record->file_path,
                                    'invoice_data' => [
                                        'invoice_number' => $record->invoice_number,
                                        'partner' => $record->partner,
                                        'message' => $data['message'] ?? null,
                                    ]
                                ],
                                $data['email']
                            );
                            
                            if ($sent) {
                                $record->update([
                                    'emailed_at' => now(),
                                    'email_recipient' => $data['email'],
                                ]);
                                \Filament\Notifications\Notification::make()
                                    ->title('Invoice Sent')
                                    ->body('Invoice sent to ' . $data['email'])
                                    ->success()
                                    ->send();
                            } else {
                                \Filament\Notifications\Notification::make()
                                    ->title('Email Failed')
                                    ->body('Failed to send invoice')
                                    ->danger()
                                    ->send();
                            }
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
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

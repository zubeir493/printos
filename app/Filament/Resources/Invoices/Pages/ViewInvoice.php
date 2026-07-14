<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Services\InvoiceGeneratorService;
use Filament\Actions;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\Action::make('mark_sent')
                    ->label('Mark Sent')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('gray')
                    ->visible(fn (): bool => in_array($this->record->status, ['draft', 'unpaid'], true))
                    ->action(function (): void {
                        $this->record->update(['status' => 'sent']);
                        $this->record->refresh();

                        Notification::make()->title('Invoice marked as sent')->success()->send();
                    }),

                Actions\Action::make('cancel_invoice')
                    ->label('Cancel Invoice')
                    ->icon('heroicon-o-x-circle')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (): bool => in_array($this->record->status, ['draft', 'sent', 'unpaid', 'partial', 'overdue'], true))
                    ->action(function (): void {
                        $this->record->update(['status' => 'cancelled']);
                        $this->record->refresh();

                        Notification::make()->title('Invoice cancelled')->success()->send();
                    }),
                Actions\Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(fn ($record) => app(InvoiceGeneratorService::class)->getInvoiceDownloadUrl($record->file_path, $record->filename))
                    ->openUrlInNewTab(),
                Actions\Action::make('email')
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
                    ->action(function (array $data): void {
                        try {
                            $invoiceService = app(InvoiceGeneratorService::class);
                            $sent = $invoiceService->sendInvoiceEmail(
                                [
                                    'filename' => $this->record->filename,
                                    'path' => $this->record->file_path,
                                    'invoice_data' => [
                                        'invoice_number' => $this->record->invoice_number,
                                        'invoice_date' => $this->record->invoice_date?->format('Y-m-d'),
                                        'partner' => $this->record->partner,
                                        'order' => (object) ['partner' => $this->record->partner],
                                        'due_date' => $this->record->due_date?->format('Y-m-d'),
                                        'total_amount' => $this->record->total_amount,
                                        'balance_due' => $this->record->balance_due,
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

                                if (! in_array($this->record->status, ['paid', 'cancelled'], true)) {
                                    $updates['status'] = 'sent';
                                }

                                $this->record->update($updates);
                                $this->record->refresh();

                                Notification::make()
                                    ->title('Invoice Sent')
                                    ->body('Invoice sent to '.$data['email'])
                                    ->success()
                                    ->send();

                                return;
                            }

                            Notification::make()
                                ->title('Email Failed')
                                ->body('Failed to send invoice')
                                ->danger()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Email Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ]),
        ];
    }
}

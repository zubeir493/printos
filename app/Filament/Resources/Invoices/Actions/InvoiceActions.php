<?php

namespace App\Filament\Resources\Invoices\Actions;

use App\Models\Invoice;
use App\Services\InvoiceGeneratorService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

class InvoiceActions
{
    public static function make(): ActionGroup
    {
        return ActionGroup::make([
            self::markSent(),
            self::cancelInvoice(),
            self::download(),
            self::email(),
        ]);
    }

    public static function editMake(string $indexUrl): ActionGroup
    {
        return ActionGroup::make([
            Action::make('save')
                ->label('Save Changes')
                ->action('save')
                ->icon('heroicon-o-check')
                ->color('gray'),
            self::markSent(),
            self::cancelInvoice(),
            Action::make('cancel')
                ->label('Cancel')
                ->url($indexUrl)
                ->icon('heroicon-o-x-mark')
                ->color('gray'),
        ]);
    }

    public static function markSent(): Action
    {
        return Action::make('mark_sent')
            ->label('Mark Sent')
            ->icon('heroicon-o-paper-airplane')
            ->color('gray')
            ->visible(fn (Invoice $record): bool => in_array($record->status, ['draft', 'unpaid'], true))
            ->action(function (Invoice $record): void {
                $record->update(['status' => 'sent']);
                Notification::make()->title('Invoice marked as sent')->success()->send();
            });
    }

    public static function cancelInvoice(): Action
    {
        return Action::make('cancel_invoice')
            ->label('Cancel Invoice')
            ->icon('heroicon-o-x-circle')
            ->color('gray')
            ->requiresConfirmation()
            ->visible(fn (Invoice $record): bool => in_array($record->status, ['draft', 'sent', 'unpaid', 'partial', 'overdue'], true))
            ->action(function (Invoice $record): void {
                $record->update(['status' => 'cancelled']);
                Notification::make()->title('Invoice cancelled')->success()->send();
            });
    }

    public static function download(): Action
    {
        return Action::make('download')
            ->label('Download')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->url(fn (Invoice $record): string => app(InvoiceGeneratorService::class)->getInvoiceDownloadUrl($record->file_path, $record->filename))
            ->openUrlInNewTab();
    }

    public static function email(): Action
    {
        return Action::make('email')
            ->label('Email Invoice')
            ->icon('heroicon-o-envelope')
            ->color('gray')
            ->form([
                TextInput::make('email')
                    ->label('Email Address')
                    ->email()
                    ->required()
                    ->default(fn (Invoice $record): ?string => $record->partner?->email ?? $record->email_recipient)
                    ->placeholder('Enter email address'),
                Textarea::make('message')
                    ->label('Message (Optional)')
                    ->placeholder('Add a custom message...')
                    ->rows(3),
            ])
            ->action(function (array $data, Invoice $record): void {
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

                    if (! $sent) {
                        Notification::make()
                            ->title('Email Failed')
                            ->body('Failed to send invoice')
                            ->danger()
                            ->send();

                        return;
                    }

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
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Email Failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}

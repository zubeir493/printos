<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Pages\CreateRecord;
use App\Services\InvoiceGeneratorService;
use Filament\Actions;
use Filament\Notifications\Notification;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    protected static ?string $title = 'Create New Invoice';

    protected bool $shouldSendEmail = false;

    protected ?string $emailRecipient = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->shouldSendEmail = (bool) ($data['send_email'] ?? false);
        $this->emailRecipient = is_string($data['email_recipient'] ?? null) ? trim($data['email_recipient']) : null;

        unset($data['send_email'], $data['email_recipient']);

        if (blank($data['filename'] ?? null)) {
            $data['filename'] = 'manual-invoice-' . uniqid('', true) . '.pdf';
        }

        if (blank($data['file_path'] ?? null)) {
            $data['file_path'] = 'invoices/' . $data['filename'];
        }

        $data['order_type'] ??= match ($data['invoice_type'] ?? 'sales') {
            'purchase' => 'purchase_order',
            'service' => 'job_order',
            'receipt' => 'payment',
            default => 'sales_order',
        };

        return $data;
    }

    protected function afterCreate(): void
    {
        if (! $this->shouldSendEmail || ! $this->record || blank($this->emailRecipient)) {
            return;
        }

        $recipient = $this->emailRecipient ?: $this->record->partner?->email;

        if (blank($recipient)) {
            return;
        }

        try {
            $invoiceService = app(InvoiceGeneratorService::class);
            $invoiceService->sendInvoiceEmail([
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
                    'company_info' => config('invoice.company', [
                        'name' => config('app.name', 'PrintOS'),
                    ]),
                ],
            ], $recipient);

            $this->record->update([
                'emailed_at' => now(),
                'email_recipient' => $recipient,
            ]);

            Notification::make()
                ->title('Email Sent')
                ->body('Invoice sent to ' . $recipient)
                ->success()
                ->send();
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('Email Failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('save')
                ->label('Save Invoice')
                ->action('save')
                ->icon('heroicon-o-check')
                ->color('success'),

            Actions\Action::make('save_and_continue')
                ->label('Save & Continue')
                ->action('saveAndContinue')
                ->icon('heroicon-o-arrow-right')
                ->color('primary'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Invoice created successfully!';
    }
}

<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Resources\Invoices\InvoiceResource;
use BackedEnum;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    protected static ?string $title = 'Edit Invoice';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-pencil-square';

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('save')
                ->label('Save Changes')
                ->action('save')
                ->icon('heroicon-o-check')
                ->color('success'),

            Actions\Action::make('mark_sent')
                ->label('Mark Sent')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->visible(fn (): bool => in_array($this->record->status, ['draft', 'unpaid'], true))
                ->action(function (): void {
                    $this->record->update(['status' => 'sent']);
                    $this->record->refresh();

                    Notification::make()->title('Invoice marked as sent')->success()->send();
                }),

            Actions\Action::make('cancel_invoice')
                ->label('Cancel Invoice')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => in_array($this->record->status, ['draft', 'sent', 'unpaid', 'partial', 'overdue'], true))
                ->action(function (): void {
                    $this->record->update(['status' => 'cancelled']);
                    $this->record->refresh();

                    Notification::make()->title('Invoice cancelled')->success()->send();
                }),

            Actions\Action::make('cancel')
                ->label('Cancel')
                ->url($this->getResource()::getUrl('index'))
                ->icon('heroicon-o-x-mark')
                ->color('gray'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Invoice updated successfully!';
    }

    protected function afterSave(): void
    {
        Notification::make()
            ->title('Invoice Updated')
            ->body('The invoice has been updated successfully.')
            ->success()
            ->send();
    }
}

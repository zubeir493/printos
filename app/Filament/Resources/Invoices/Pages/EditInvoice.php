<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Resources\Invoices\Actions\InvoiceActions;
use App\Filament\Resources\Invoices\InvoiceResource;
use BackedEnum;
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
            InvoiceActions::editMake($this->getResource()::getUrl('index')),
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

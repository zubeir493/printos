<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Resources\Invoices\InvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use BackedEnum;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    protected static ?string $title = 'Create New Invoice';

    // protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-plus-circle';

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

    protected function afterCreate(): void
    {
        Notification::make()
            ->title('Invoice Created')
            ->body('The invoice has been created and is ready for further processing.')
            ->success()
            ->send();
    }
}

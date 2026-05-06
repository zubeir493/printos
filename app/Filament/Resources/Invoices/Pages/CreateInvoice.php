<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Pages\CreateRecord;
use Filament\Actions;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    protected static ?string $title = 'Create New Invoice';

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

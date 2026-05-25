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
            Actions\DeleteAction::make()
                ->label('Delete Invoice')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Delete Invoice')
                ->modalDescription('Are you sure you want to delete this invoice? This action cannot be undone.')
                ->modalSubmitActionLabel('Yes, delete it'),

            Actions\Action::make('save')
                ->label('Save Changes')
                ->action('save')
                ->icon('heroicon-o-check')
                ->color('success'),

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

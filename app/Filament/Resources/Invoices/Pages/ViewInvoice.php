<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Services\InvoiceGeneratorService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
            Actions\ActionGroup::make([
                Actions\Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(fn ($record) => app(InvoiceGeneratorService::class)->getInvoiceDownloadUrl($record->file_path, $record->filename))
                    ->openUrlInNewTab(),
            ])
                ->label('More actions')
                ->color('gray'),
        ];
    }
}

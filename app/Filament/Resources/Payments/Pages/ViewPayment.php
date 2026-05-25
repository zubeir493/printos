<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use App\Services\Accounting\VoidPaymentJournalEntry;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('void')
                ->label('Void Payment')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Void Payment')
                ->modalDescription('This will post a reversing journal entry and mark the original payment as voided.')
                ->form([
                    Textarea::make('reason')
                        ->label('Reason')
                        ->required()
                        ->maxLength(500)
                        ->rows(4),
                ])
                ->visible(fn ($record) => blank($record->voided_at))
                ->action(function (array $data, $record) {
                    app(VoidPaymentJournalEntry::class)->handle($record, $data['reason'], auth()->user());

                    Notification::make()
                        ->title('Payment Voided Successfully')
                        ->success()
                        ->send();

                    $this->redirect(static::getUrl(['record' => $record]));
                }),
        ];
    }
}

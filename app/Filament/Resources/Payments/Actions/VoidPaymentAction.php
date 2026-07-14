<?php

namespace App\Filament\Resources\Payments\Actions;

use App\Models\Payment;
use App\Services\Accounting\VoidPaymentJournalEntry;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;

class VoidPaymentAction
{
    public static function make(): Action
    {
        return Action::make('void')
            ->label('Void Payment')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('gray')
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
            ->visible(fn (Payment $record): bool => Gate::allows('void', $record))
            ->action(function (array $data, Payment $record): void {
                app(VoidPaymentJournalEntry::class)->handle($record, $data['reason'], auth()->user());

                Notification::make()
                    ->title('Payment voided successfully')
                    ->success()
                    ->send();
            });
    }
}

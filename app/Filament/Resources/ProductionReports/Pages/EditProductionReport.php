<?php

namespace App\Filament\Resources\ProductionReports\Pages;

use App\Filament\Resources\ProductionReports\ProductionReportResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditProductionReport extends EditRecord
{
    protected static string $resource = ProductionReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('submit')
                    ->label('Submit Report')
                    ->icon('heroicon-o-check-circle')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === 'draft')
                    ->action(function ($record) {
                        $record->update(['status' => 'submitted']);
                        $this->record->refresh();
                        $this->refreshFormData(['status']);

                        Notification::make()
                            ->title('Production report submitted')
                            ->success()
                            ->send();
                    }),
                DeleteAction::make()
                    ->visible(fn ($record) => $record->status === 'draft'),
            ]),
        ];
    }
}

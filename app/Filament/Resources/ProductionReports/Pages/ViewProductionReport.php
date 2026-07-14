<?php

namespace App\Filament\Resources\ProductionReports\Pages;

use App\Filament\Resources\ProductionReports\ProductionReportResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewProductionReport extends ViewRecord
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

                        Notification::make()
                            ->title('Production report submitted')
                            ->success()
                            ->send();
                    }),
                EditAction::make()
                    ->visible(fn ($record) => $record->status === 'draft')
                    ->color('gray'),
            ]),
        ];
    }
}

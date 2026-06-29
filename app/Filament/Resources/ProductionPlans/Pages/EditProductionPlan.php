<?php

namespace App\Filament\Resources\ProductionPlans\Pages;

use App\Filament\Resources\ProductionPlans\ProductionPlanResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditProductionPlan extends EditRecord
{
    protected static string $resource = ProductionPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('approve')
                    ->label('Approve Plan')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === 'draft')
                    ->action(function ($record) {
                        $record->update(['status' => 'approved']);
                        $this->record->refresh();
                        $this->refreshFormData(['status']);

                        Notification::make()
                            ->title('Production plan approved')
                            ->success()
                            ->send();
                    }),
                DeleteAction::make()
                    ->visible(fn ($record) => $record->status === 'draft'),
            ]),
        ];
    }
}

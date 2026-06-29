<?php

namespace App\Filament\Resources\CostEstimates\Pages;

use App\Filament\Resources\CostEstimates\CostEstimateResource;
use App\Services\Costing\CostEstimateService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCostEstimate extends EditRecord
{
    protected static string $resource = CostEstimateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('finalize')
                    ->label('Finalize')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (): bool => $this->record->status === 'draft')
                    ->action(function (): void {
                        app(CostEstimateService::class)->finalize($this->record);
                        Notification::make()->title('Estimate finalized')->success()->send();
                        $this->redirect(CostEstimateResource::getUrl('view', ['record' => $this->record]));
                    }),
                DeleteAction::make()
                    ->visible(fn (): bool => $this->record->status === 'draft'),
            ]),
        ];
    }

    protected function afterSave(): void
    {
        app(CostEstimateService::class)->recalculate($this->record);
    }
}

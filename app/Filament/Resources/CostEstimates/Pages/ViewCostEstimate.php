<?php

namespace App\Filament\Resources\CostEstimates\Pages;

use App\Filament\Resources\CostEstimates\CostEstimateResource;
use App\Services\Costing\CostEstimateService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Colors\Color;

class ViewCostEstimate extends ViewRecord
{
    protected static string $resource = CostEstimateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn (): bool => $this->record->isEditable())
                ->color(Color::Indigo),
            Action::make('finalize')
                ->label('Finalize')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->status === 'draft')
                ->action(function (): void {
                    app(CostEstimateService::class)->finalize($this->record);
                    Notification::make()->title('Estimate finalized')->success()->send();
                }),
            Action::make('create_proforma')
                ->label('Create Proforma')
                ->icon('heroicon-o-document-text')
                ->color(Color::Indigo)
                ->visible(fn (): bool => in_array($this->record->status, ['finalized'], true))
                ->action(function (): void {
                    $proforma = app(CostEstimateService::class)->createProforma($this->record);
                    Notification::make()->title('Proforma created')->body($proforma->proforma_number)->success()->send();
                }),
        ];
    }
}

<?php

namespace App\Filament\Resources\StockAdjustments\Pages;

use App\Filament\Resources\StockAdjustments\StockAdjustmentResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Colors\Color;

class ViewStockAdjustment extends ViewRecord
{
    protected static string $resource = StockAdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->color(Color::Indigo),
            Action::make('post')
                ->label('Post Adjustment')
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->requiresConfirmation()
                ->visible(fn ($record) => $record->status === 'draft')
                ->action(function ($record) {
                    $record->post();
                    Notification::make()
                        ->title('Adjustment Posted Successfully')
                        ->success()
                        ->send();
                }),
        ];
    }
}

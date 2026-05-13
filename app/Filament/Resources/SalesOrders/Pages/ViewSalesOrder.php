<?php

namespace App\Filament\Resources\SalesOrders\Pages;

use App\Filament\Resources\SalesOrders\SalesOrderResource;
use App\Filament\Support\PanelAccess;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewSalesOrder extends ViewRecord
{
    protected static string $resource = SalesOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn ($record) => $record->status !== 'completed'),
            Action::make('complete')
                ->label('Complete Sale')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn ($record) => 
                    $record->status === 'draft' && 
                    PanelAccess::canManageSalesOrders()
                )
                ->requiresConfirmation()
                ->modalHeading('Complete this Sales Order?')
                ->modalDescription('This will mark the sale as completed and deduct inventory.')
                ->action(function ($record) {
                    try {
                        $record->update(['status' => 'completed']);
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Cannot complete this order')
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Sales Order Completed')
                        ->body($record->order_number.' has been completed.')
                        ->success()
                        ->send();
                }),
        ];
    }
}

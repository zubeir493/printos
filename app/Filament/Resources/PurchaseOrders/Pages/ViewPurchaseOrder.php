<?php
 
 namespace App\Filament\Resources\PurchaseOrders\Pages;
 
 use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
 use App\Filament\Support\PanelAccess;
 use Filament\Actions;
 use Filament\Actions\Action;
 use Filament\Notifications\Notification;
 use Filament\Resources\Pages\ViewRecord;
 use Filament\Support\Colors\Color;
 
 class ViewPurchaseOrder extends ViewRecord
 {
     protected static string $resource = PurchaseOrderResource::class;
 
     protected function getHeaderActions(): array
     {
         return [
             Action::make('approve')
                 ->label('Approve')
                 ->icon('heroicon-o-check-circle')
                 ->color('success')
                 ->visible(fn ($record) => $record->status === 'draft' && PanelAccess::canManagePurchaseOrders())
                 ->requiresConfirmation()
                 ->modalHeading('Approve this Purchase Order?')
                 ->modalDescription('This marks the purchase order as approved and ready for receiving.')
                 ->action(function ($record) {
                     $record->update(['status' => 'approved']);
                     Notification::make()->title('Purchase order approved')->success()->send();
                 }),

             Action::make('receive')
                ->label('Receive Items')
                ->icon('heroicon-o-archive-box-arrow-down')
                ->color('primary')
                ->visible(fn ($record) => $record->status === 'approved' && PanelAccess::canAccessWarehouseSection())
                ->requiresConfirmation()
                ->modalHeading('Receive Items')
                ->modalDescription('Use the Goods Receipts tab below to record received items. The order status will automatically update to "received" when all items are fully received.')
                ->action(function ($record) {
                    Notification::make()
                        ->title('Ready to Receive Items')
                        ->body('Use the Goods Receipts tab below to add receipts.')
                        ->info()
                        ->send();
                }),

             Action::make('mark_received')
                 ->label('Mark as Received')
                 ->icon('heroicon-o-check-circle')
                 ->color('success')
                 ->visible(fn ($record) => 
                     $record->status === 'approved' && 
                     PanelAccess::canManagePurchaseOrders() && 
                     $record->goodsReceipts()->exists()
                 )
                 ->requiresConfirmation()
                 ->modalHeading('Mark Purchase Order as Received')
                 ->modalDescription('Manually mark this purchase order as received? Use this if you want to close the PO even if quantities are not fully received.')
                 ->action(function ($record) {
                     $record->update(['status' => 'received']);
                     Notification::make()->title('Purchase order marked as received')->success()->send();
                 }),

             Action::make('cancel')
                 ->label('Cancel')
                 ->icon('heroicon-o-x-circle')
                 ->color('danger')
                 ->visible(fn ($record) => in_array($record->status, ['draft', 'approved']) && PanelAccess::canManagePurchaseOrders())
                 ->requiresConfirmation()
                 ->modalHeading('Cancel Purchase Order')
                 ->modalDescription('Cancel this purchase order? This action cannot be undone.')
                 ->action(function ($record) {
                     $record->update(['status' => 'cancelled']);
                     Notification::make()->title('Purchase order cancelled')->danger()->send();
                 }),

             Actions\EditAction::make()
                 ->visible(fn ($record) => $record->status === 'draft' && PanelAccess::canManagePurchaseOrders()),
         ];
     }
 }
 

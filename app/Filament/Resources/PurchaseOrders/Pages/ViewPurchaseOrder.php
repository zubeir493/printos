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
                ->modalHeading('Receive Items')
                ->modalDescription(fn ($record) => "Record stock received against {$record->po_number}. Items will be added to inventory immediately.")
                ->schema([
                    \Filament\Schemas\Components\Grid::make(2)->schema([
                        \Filament\Forms\Components\Select::make('warehouse_id')
                            ->label('Receiving Warehouse')
                            ->options(\App\Models\Warehouse::pluck('name', 'id'))
                            ->default(fn () => \App\Models\Warehouse::where('is_default', true)->value('id'))
                            ->searchable()
                            ->preload()
                            ->required(),
                        \Filament\Forms\Components\DatePicker::make('receipt_date')
                            ->label('Receipt Date')
                            ->default(now())
                            ->required(),
                    ]),
                    \Filament\Forms\Components\Repeater::make('items')
                        ->label('Items to Receive')
                        ->table([
                            \Filament\Forms\Components\Repeater\TableColumn::make('Item')->alignLeft(),
                            \Filament\Forms\Components\Repeater\TableColumn::make('Ordered Quantity')->alignLeft(),
                            \Filament\Forms\Components\Repeater\TableColumn::make('Already Received')->alignLeft(),
                            \Filament\Forms\Components\Repeater\TableColumn::make('Quantity')->alignLeft(),
                        ])
                        ->compact()
                        ->schema([
                            \Filament\Forms\Components\Hidden::make('purchase_order_item_id'),
                            \Filament\Forms\Components\Placeholder::make('item_name')
                                ->content(fn ($get) => \App\Models\PurchaseOrderItem::with('inventoryItem')
                                    ->find($get('purchase_order_item_id'))
                                    ?->inventoryItem?->name ?? '—'),
                            \Filament\Forms\Components\Placeholder::make('ordered')
                                ->content(fn ($get) => \App\Models\PurchaseOrderItem::find($get('purchase_order_item_id'))
                                    ?->quantity ?? '—'),
                            \Filament\Forms\Components\Placeholder::make('already_received')
                                ->content(fn ($get) => \App\Models\PurchaseOrderItem::find($get('purchase_order_item_id'))
                                    ?->received_quantity ?? '0'),
                            \Filament\Forms\Components\TextInput::make('quantity_received')
                                ->numeric()
                                ->minValue(0)
                                ->required(),
                        ])
                        ->columns(4)
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->default(fn ($record) => $record->purchaseOrderItems()
                            ->with('inventoryItem')
                            ->get()
                            ->map(fn ($item) => [
                                'purchase_order_item_id' => $item->id,
                                'quantity_received'      => max(0, $item->quantity - $item->received_quantity),
                            ])
                            ->toArray()
                        )
                        ->columnSpanFull(),
                ])
                ->action(function ($record, array $data) {
                    try {
                        \Illuminate\Support\Facades\DB::transaction(function () use ($record, $data) {
                            $lastNumber = \App\Models\GoodsReceipt::max('id') ?? 0;
                            $receiptNumber = 'GR-'.str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT);

                            $receipt = \App\Models\GoodsReceipt::create([
                                'receipt_number'    => $receiptNumber,
                                'purchase_order_id' => $record->id,
                                'warehouse_id'      => $data['warehouse_id'],
                                'receipt_date'      => $data['receipt_date'],
                                'status'            => 'draft',
                            ]);

                            foreach ($data['items'] as $item) {
                                if (($item['quantity_received'] ?? 0) <= 0) {
                                    continue;
                                }

                                $receipt->items()->create([
                                    'purchase_order_item_id' => $item['purchase_order_item_id'],
                                    'quantity_received'      => $item['quantity_received'],
                                ]);
                            }

                            // Post immediately — triggers GoodsReceiptObserver which updates inventory
                            $receipt->update(['status' => 'posted']);
                        });

                        Notification::make()
                            ->title('Items Received')
                            ->body('Stock has been added to inventory.')
                            ->success()
                            ->send();

                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Failed to Receive Items')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
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
 

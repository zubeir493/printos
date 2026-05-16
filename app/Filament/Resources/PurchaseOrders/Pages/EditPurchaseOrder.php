<?php

namespace App\Filament\Resources\PurchaseOrders\Pages;

use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Support\PanelAccess;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\Warehouse;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Grid;

class EditPurchaseOrder extends EditRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    public static function canAccess($record = null): bool
    {
        return PanelAccess::canManagePurchaseOrders();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('receive_items')
                ->label('Receive Items')
                ->icon('heroicon-o-truck')
                ->color('success')
                ->visible(fn (PurchaseOrder $record) => PanelAccess::canAccessWarehouseSection() && ! in_array($record->status, ['draft', 'cancelled']))
                ->form([
                    Grid::make(2)->schema([
                        Select::make('warehouse_id')
                            ->label('Receiving Warehouse')
                            ->options(Warehouse::pluck('name', 'id'))
                            ->default(fn () => Warehouse::where('is_default', true)->value('id'))
                            ->searchable()
                            ->preload()
                            ->required(),
                        DatePicker::make('receipt_date')
                            ->label('Receipt Date')
                            ->default(now())
                            ->required(),
                    ]),
                    Repeater::make('items')
                        ->label('Items to Receive')
                        ->table([
                            TableColumn::make('Item')->alignLeft(),
                            TableColumn::make('Ordered Quantity')->alignLeft(),
                            TableColumn::make('Already Received')->alignLeft(),
                            TableColumn::make('Quantity')->alignLeft(),
                        ])
                        ->compact()
                        ->schema([
                            Hidden::make('purchase_order_item_id'),
                            TextInput::make('product_name')->disabled(),
                            TextInput::make('ordered_quantity')->disabled(),
                            TextInput::make('already_received_quantity')->disabled(),
                            TextInput::make('quantity_to_receive')
                                ->numeric()
                                ->required()
                                ->rule(function ($get) {
                                    return function (string $attribute, $value, \Closure $fail) use ($get) {
                                        $ordered = (float) $get('ordered_quantity');
                                        $received = (float) $get('already_received_quantity');
                                        if ((float) $value + $received > $ordered) {
                                            $fail('Cannot over-receive items.');
                                        }
                                    };
                                }),
                        ])
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false),
                ])
                ->fillForm(function (PurchaseOrder $record) {
                    $items = $record->purchaseOrderItems->map(function ($item) {
                        return [
                            'purchase_order_item_id' => $item->id,
                            'product_name' => $item->inventoryItem->name ?? 'Unknown',
                            'ordered_quantity' => $item->quantity,
                            'already_received_quantity' => $item->received_quantity ?? 0,
                            'quantity_to_receive' => max(0, $item->quantity - ($item->received_quantity ?? 0)),
                        ];
                    })->toArray();

                    return ['items' => $items];
                })
                ->action(function (array $data, PurchaseOrder $record) {
                    $itemsToReceive = collect($data['items'])->filter(function ($item) {
                        return (float) $item['quantity_to_receive'] > 0;
                    });

                    if ($itemsToReceive->isEmpty()) {
                        Notification::make()->title('No items to receive')->warning()->send();

                        return;
                    }

                    $receipt = GoodsReceipt::create([
                        'purchase_order_id' => $record->id,
                        'warehouse_id' => $data['warehouse_id'],
                        'receipt_date' => now(),
                        'status' => 'draft',
                    ]);

                    foreach ($itemsToReceive as $item) {
                        $receipt->items()->create([
                            'purchase_order_item_id' => $item['purchase_order_item_id'],
                            'quantity_received' => $item['quantity_to_receive'],
                        ]);
                    }

                    $receipt->update(['status' => 'posted']);

                    // Check if all items are received
                    $allReceived = true;
                    foreach ($record->purchaseOrderItems()->get() as $poItem) {
                        if ($poItem->quantity > ($poItem->received_quantity ?? 0)) {
                            $allReceived = false;
                            break;
                        }
                    }
                    if ($allReceived && $record->status !== 'received') {
                        $record->update(['status' => 'received']);
                    }

                    Notification::make()->title('Items received successfully')->success()->send();
                }),
            DeleteAction::make(),
        ];
    }
}

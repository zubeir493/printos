<?php

namespace App\Filament\Resources\PurchaseOrders\Pages;

use App\Enums\PaymentTransactionType;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Support\PanelAccess;
use App\Models\Bank;
use App\Models\GoodsReceipt;
use App\Models\Payment;
use App\Models\PurchaseOrderItem;
use App\Models\Warehouse;
use App\Support\Money;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;

class ViewPurchaseOrder extends ViewRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->getRecord()->po_number;
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('gray')
                    ->visible(fn () => PanelAccess::canManagePurchaseOrders())
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
                    ->color('gray')
                    ->visible(fn () => PanelAccess::canAccessWarehouseSection())
                    ->modalHeading('Receive Items')
                    ->modalDescription(fn ($record) => "Record stock received against {$record->po_number}. Items will be added to inventory immediately.")
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('warehouse_id')
                                ->label('Receiving Warehouse')
                                ->options(fn (): array => Warehouse::query()->orderBy('name')->pluck('name', 'id')->all())
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
                                Placeholder::make('item_name')
                                    ->content(fn ($get) => PurchaseOrderItem::with('inventoryItem')
                                        ->find($get('purchase_order_item_id'))
                                        ?->inventoryItem?->name ?? '—'),
                                Placeholder::make('ordered')
                                    ->content(fn ($get) => PurchaseOrderItem::find($get('purchase_order_item_id'))
                                        ?->quantity ?? '—'),
                                Placeholder::make('already_received')
                                    ->content(fn ($get) => PurchaseOrderItem::find($get('purchase_order_item_id'))
                                        ?->received_quantity ?? '0'),
                                TextInput::make('quantity_received')
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
                                    'quantity_received' => max(0, $item->quantity - $item->received_quantity),
                                ])
                                ->toArray()
                            )
                            ->columnSpanFull(),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            DB::transaction(function () use ($record, $data) {
                                $receipt = GoodsReceipt::create([
                                    'purchase_order_id' => $record->id,
                                    'warehouse_id' => $data['warehouse_id'],
                                    'receipt_date' => $data['receipt_date'],
                                    'status' => 'draft',
                                ]);

                                foreach ($data['items'] as $item) {
                                    if (($item['quantity_received'] ?? 0) <= 0) {
                                        continue;
                                    }

                                    $receipt->items()->create([
                                        'purchase_order_item_id' => $item['purchase_order_item_id'],
                                        'quantity_received' => $item['quantity_received'],
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
                    ->color('gray')
                    ->visible(fn ($record) => $record !== null &&
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
                    ->color('gray')
                    ->visible(fn () => PanelAccess::canManagePurchaseOrders())
                    ->requiresConfirmation()
                    ->modalHeading('Cancel Purchase Order')
                    ->modalDescription('Cancel this purchase order? This action cannot be undone.')
                    ->action(function ($record) {
                        $record->update(['status' => 'cancelled']);
                        Notification::make()->title('Purchase order cancelled')->danger()->send();
                    }),

                Actions\EditAction::make()
                    ->visible(fn () => PanelAccess::canManagePurchaseOrders())
                    ->color('gray'),
                Action::make('pay')
                    ->label('Pay')
                    ->icon('heroicon-o-banknotes')
                    ->color('gray')
                    ->visible(fn ($record) => $record->balance > 0
                        && PanelAccess::canAccessFinanceSection())
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('method')
                                ->label('Payment method')
                                ->options([
                                    'cash' => 'Cash',
                                    'bank' => 'Bank Transfer',
                                    'cheque' => 'Cheque',
                                ])
                                ->default('bank')
                                ->required()
                                ->live(),
                            Select::make('bank_id')
                                ->label('Bank Account')
                                ->options(fn (): array => Bank::query()->orderBy('name')->pluck('name', 'id')->all())
                                ->searchable()
                                ->preload()
                                ->visible(fn (callable $get): bool => $get('method') === 'bank')
                                ->required(fn (callable $get): bool => $get('method') === 'bank'),
                            TextInput::make('amount')
                                ->label('Payment Amount')
                                ->required()
                                ->numeric()
                                ->suffix(fn (): string => Money::suffix())
                                ->default(fn ($record) => $record->balance)
                                ->maxValue(fn ($record): float => $record->balance)
                                ->helperText(fn ($record) => 'Balance: '.Money::format($record->balance)),
                            DatePicker::make('payment_date')
                                ->label('Payment Date')
                                ->default(now())
                                ->required(),
                            TextInput::make('reference')
                                ->label('Memo / Reference')
                                ->placeholder('Receipt number, cheque number, or short note')
                                ->maxLength(255),
                        ]),
                    ])
                    ->action(function ($record, array $data): void {
                        try {
                            DB::transaction(function () use ($record, $data): void {
                                $lockedRecord = $record->newQuery()
                                    ->lockForUpdate()
                                    ->findOrFail($record->getKey());
                                $amount = (float) $data['amount'];

                                if ($amount > $lockedRecord->balance) {
                                    throw new \Exception('Cannot pay more than the remaining balance of '.Money::format($lockedRecord->balance).'.');
                                }

                                Payment::create([
                                    'partner_id' => $lockedRecord->partner_id,
                                    'payment_date' => $data['payment_date'],
                                    'transaction_type' => PaymentTransactionType::SUPPLIER_PAYMENT->value,
                                    'amount' => $amount,
                                    'method' => $data['method'],
                                    'bank_id' => $data['bank_id'] ?? null,
                                    'reference' => $data['reference'] ?? 'Payment for '.$lockedRecord->po_number,
                                    'payable_type' => get_class($lockedRecord),
                                    'payable_id' => $lockedRecord->id,
                                ]);
                            });

                            Notification::make()
                                ->title('Payment Recorded')
                                ->body(Money::format($data['amount']).' paid against '.$record->po_number.'.')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Payment Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ]),
        ];
    }
}

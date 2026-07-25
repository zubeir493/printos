<?php

namespace App\Filament\Resources\PurchaseOrders\Actions;

use App\Enums\PaymentTransactionType;
use App\Filament\Support\PanelAccess;
use App\Models\Bank;
use App\Models\GoodsReceipt;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Warehouse;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\Facades\DB;

class PurchaseOrderActions
{
    public static function make(bool $includeDelete = false): ActionGroup
    {
        return ActionGroup::make(array_filter([
            self::approve(),
            self::receive(),
            self::markReceived(),
            self::cancel(),
            self::edit(),
            self::pay(),
            $includeDelete ? self::delete() : null,
        ]));
    }

    public static function approve(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->icon('heroicon-o-check-circle')
            ->color('gray')
            ->visible(fn (PurchaseOrder $record): bool => $record->status === 'draft' && PanelAccess::canManagePurchaseOrders())
            ->requiresConfirmation()
            ->modalHeading('Approve this Purchase Order?')
            ->modalDescription('This marks the purchase order as approved and ready for receiving.')
            ->action(function (PurchaseOrder $record): void {
                $record->update(['status' => 'approved']);
                Notification::make()->title('Purchase order approved')->success()->send();
            });
    }

    public static function receive(): Action
    {
        return Action::make('receive')
            ->label('Receive Items')
            ->icon('heroicon-o-archive-box-arrow-down')
            ->color('gray')
            ->visible(fn (PurchaseOrder $record): bool => $record->status === 'approved' && PanelAccess::canAccessWarehouseSection())
            ->modalHeading('Receive Items')
            ->modalDescription(fn (PurchaseOrder $record): string => "Record stock received against {$record->po_number}. Items will be added to inventory immediately.")
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
                                ?->inventoryItem?->name ?? '-'),
                        Placeholder::make('ordered')
                            ->content(fn ($get) => PurchaseOrderItem::find($get('purchase_order_item_id'))
                                ?->quantity ?? '-'),
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
                    ->default(fn (PurchaseOrder $record): array => $record->purchaseOrderItems()
                        ->with('inventoryItem')
                        ->get()
                        ->map(fn ($item): array => [
                            'purchase_order_item_id' => $item->id,
                            'quantity_received' => max(0, $item->quantity - $item->received_quantity),
                        ])
                        ->toArray())
                    ->columnSpanFull(),
            ])
            ->action(function (PurchaseOrder $record, array $data): void {
                try {
                    DB::transaction(function () use ($record, $data): void {
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
            });
    }

    public static function markReceived(): Action
    {
        return Action::make('mark_received')
            ->label('Mark as Received')
            ->icon('heroicon-o-check-circle')
            ->color('gray')
            ->visible(fn (PurchaseOrder $record): bool => $record->status === 'approved'
                && PanelAccess::canManagePurchaseOrders()
                && $record->goodsReceipts()->exists())
            ->requiresConfirmation()
            ->modalHeading('Mark Purchase Order as Received')
            ->modalDescription('Manually mark this purchase order as received? Use this if you want to close the PO even if quantities are not fully received.')
            ->action(function (PurchaseOrder $record): void {
                $record->update(['status' => 'received']);
                Notification::make()->title('Purchase order marked as received')->success()->send();
            });
    }

    public static function cancel(): Action
    {
        return Action::make('cancel')
            ->label('Cancel')
            ->icon('heroicon-o-x-circle')
            ->color('gray')
            ->visible(fn (PurchaseOrder $record): bool => in_array($record->status, ['draft', 'approved'], true) && PanelAccess::canManagePurchaseOrders())
            ->requiresConfirmation()
            ->modalHeading('Cancel Purchase Order')
            ->modalDescription('Cancel this purchase order? This action cannot be undone.')
            ->action(function (PurchaseOrder $record): void {
                $record->update(['status' => 'cancelled']);
                Notification::make()->title('Purchase order cancelled')->danger()->send();
            });
    }

    public static function edit(): EditAction
    {
        return EditAction::make()
            ->color('gray')
            ->visible(fn (PurchaseOrder $record): bool => $record->status === 'draft' && PanelAccess::canManagePurchaseOrders());
    }

    public static function pay(): Action
    {
        return Action::make('pay')
            ->label('Pay')
            ->icon('heroicon-o-banknotes')
            ->color('gray')
            ->visible(fn (PurchaseOrder $record): bool => $record->balance > 0
                && PanelAccess::canAccessFinanceSection()
                && in_array($record->status, ['approved', 'received'], true))
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
                        ->visible(fn (callable $get): bool => in_array($get('method'), ['bank', 'bank_transfer', 'cheque', 'check'], true))
                        ->required(fn (callable $get): bool => in_array($get('method'), ['bank', 'bank_transfer', 'cheque', 'check'], true)),
                    TextInput::make('amount')
                        ->label('Payment Amount')
                        ->required()
                        ->numeric()
                        ->live(onBlur: true)
                        ->suffix(fn (): string => Money::suffix())
                        ->default(fn (PurchaseOrder $record): mixed => $record->balance)
                        ->maxValue(fn (PurchaseOrder $record): float => $record->balance)
                        ->helperText(fn (PurchaseOrder $record): string => 'Balance: '.Money::format($record->balance)),
                    TextInput::make('withholding_amount')
                        ->label('Withholding')
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->maxValue(fn (callable $get): float => (float) ($get('amount') ?? 0))
                        ->suffix(fn (): string => Money::suffix()),
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
            ->action(function (PurchaseOrder $record, array $data): void {
                try {
                    DB::transaction(function () use ($record, $data): void {
                        $lockedRecord = $record->newQuery()
                            ->lockForUpdate()
                            ->findOrFail($record->getKey());
                        $amount = (float) $data['amount'];
                        $withholdingAmount = (float) ($data['withholding_amount'] ?? 0);

                        if ($amount > $lockedRecord->balance) {
                            throw new \Exception('Cannot pay more than the remaining balance of '.Money::format($lockedRecord->balance).'.');
                        }

                        if ($withholdingAmount > $amount) {
                            throw new \Exception('Withholding cannot be greater than the settled payment amount.');
                        }

                        Payment::create([
                            'partner_id' => $lockedRecord->partner_id,
                            'payment_date' => $data['payment_date'],
                            'transaction_type' => PaymentTransactionType::SUPPLIER_PAYMENT->value,
                            'amount' => $amount,
                            'withholding_amount' => $withholdingAmount,
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
            });
    }

    public static function delete(): DeleteAction
    {
        return DeleteAction::make()
            ->visible(fn (PurchaseOrder $record): bool => $record->status === 'draft' && PanelAccess::canManagePurchaseOrders());
    }
}

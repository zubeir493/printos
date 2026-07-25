<?php

namespace App\Filament\Resources\SalesOrders\Actions;

use App\Enums\PaymentTransactionType;
use App\Filament\Support\PanelAccess;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use App\Services\Accounting\VoidPaymentJournalEntry;
use App\Services\InvoiceGeneratorService;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\Facades\DB;

class SalesOrderActions
{
    public static function make(): ActionGroup
    {
        return ActionGroup::make([
            self::edit(),
            self::complete(),
            self::submitItems(),
            self::pay(),
            self::invoice(),
            self::void(),
        ]);
    }

    public static function edit(): EditAction
    {
        return EditAction::make()
            ->color('gray')
            ->visible(fn (SalesOrder $record): bool => $record->status === SalesOrder::STATUS_DRAFT);
    }

    public static function complete(): Action
    {
        return Action::make('complete')
            ->label('Complete Sale')
            ->icon('heroicon-o-check-circle')
            ->color('gray')
            ->visible(fn (SalesOrder $record): bool => $record->status === SalesOrder::STATUS_DRAFT
                && $record->isCashSale()
                && PanelAccess::canManageSalesOrders())
            ->requiresConfirmation()
            ->modalHeading('Complete this Sales Order?')
            ->modalDescription('This will mark the sale as completed and deduct inventory.')
            ->action(function (SalesOrder $record): void {
                try {
                    $record->update(['status' => SalesOrder::STATUS_COMPLETED]);
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
            });
    }

    public static function submitItems(): Action
    {
        return Action::make('submit_items')
            ->label('Submit Items')
            ->icon('heroicon-o-paper-airplane')
            ->color('gray')
            ->visible(fn (SalesOrder $record): bool => $record->status === SalesOrder::STATUS_DRAFT
                && $record->payment_mode === 'credit'
                && PanelAccess::canManageSalesOrders())
            ->requiresConfirmation()
            ->modalHeading('Submit items for this credit sale?')
            ->modalDescription('This will post the sale and deduct inventory. The order will only be completed after full payment is received.')
            ->action(function (SalesOrder $record): void {
                try {
                    $record->update([
                        'status' => $record->isPaidInFull()
                            ? SalesOrder::STATUS_COMPLETED
                            : SalesOrder::STATUS_SUBMITTED,
                    ]);
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Cannot submit this order')
                        ->body($e->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Sales Order Submitted')
                    ->body($record->order_number.' items have been submitted.')
                    ->success()
                    ->send();
            });
    }

    public static function pay(): Action
    {
        return Action::make('pay')
            ->label('Receive Payment')
            ->icon('heroicon-o-banknotes')
            ->color('gray')
            ->visible(fn (SalesOrder $record): bool => $record->payment_mode === 'credit'
                && $record->status !== SalesOrder::STATUS_VOID
                && $record->balance > 0
                && PanelAccess::canAccessFinanceSection()
                && in_array($record->status, [SalesOrder::STATUS_DRAFT, SalesOrder::STATUS_SUBMITTED], true))
            ->schema([
                Grid::make(2)->schema([
                    Select::make('method')
                        ->label('Payment method')
                        ->options([
                            'cash' => 'Cash',
                            'bank' => 'Bank Transfer',
                            'cheque' => 'Cheque',
                        ])
                        ->default('cash')
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
                        ->default(fn (SalesOrder $record): mixed => $record->balance)
                        ->maxValue(fn (SalesOrder $record): float => $record->balance)
                        ->helperText(fn (SalesOrder $record): string => 'Balance: '.Money::format($record->balance)),
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
            ->action(function (SalesOrder $record, array $data): void {
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
                            'transaction_type' => PaymentTransactionType::CUSTOMER_RECEIPT->value,
                            'amount' => $amount,
                            'withholding_amount' => $withholdingAmount,
                            'method' => $data['method'],
                            'bank_id' => $data['bank_id'] ?? null,
                            'reference' => $data['reference'] ?? 'Payment for '.$lockedRecord->order_number,
                            'payable_type' => SalesOrder::class,
                            'payable_id' => $lockedRecord->id,
                        ]);
                    });

                    Notification::make()
                        ->title('Payment Recorded')
                        ->body(Money::format($data['amount']).' received for '.$record->order_number.'.')
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

    public static function invoice(): Action
    {
        return Action::make('invoice')
            ->label('Invoice')
            ->icon('heroicon-o-document-text')
            ->color('gray')
            ->hidden(fn (SalesOrder $record): bool => $record->invoices()->exists() || ! PanelAccess::canSeeMoneyValues() || $record->balance <= 0)
            ->action(function (SalesOrder $record): void {
                try {
                    $invoiceService = app(InvoiceGeneratorService::class);
                    $result = $invoiceService->generateFromSalesOrder($record);

                    Notification::make()
                        ->title('Invoice Generated')
                        ->body('Invoice '.$result['invoice_data']['invoice_number'].' created successfully.')
                        ->success()
                        ->actions([
                            Action::make('download')
                                ->label('Download')
                                ->url($invoiceService->getInvoicePath($result['filename']))
                                ->openUrlInNewTab(),
                        ])
                        ->send();
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Invoice Action Failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public static function void(): Action
    {
        return Action::make('void')
            ->label('Void')
            ->icon('heroicon-o-x-circle')
            ->color('gray')
            ->visible(fn (SalesOrder $record): bool => in_array($record->status, [SalesOrder::STATUS_SUBMITTED, SalesOrder::STATUS_COMPLETED], true))
            ->requiresConfirmation()
            ->modalHeading('Void Sales Order')
            ->modalDescription('Are you sure you want to void this sales order? This action cannot be undone.')
            ->modalSubmitActionLabel('Void Order')
            ->action(function (SalesOrder $record): void {
                try {
                    DB::transaction(function () use ($record): void {
                        $record->load(['payments', 'salesOrderItems.inventoryItem']);

                        foreach ($record->payments as $payment) {
                            if ($payment->voided_at) {
                                continue;
                            }

                            try {
                                app(VoidPaymentJournalEntry::class)->handle($payment, 'Sales order voided');
                            } catch (\Exception $e) {
                                if (! str_contains($e->getMessage(), 'No posted journal entry was found')) {
                                    throw $e;
                                }

                                $payment->update([
                                    'voided_at' => now(),
                                    'voided_by' => auth()->id(),
                                    'void_reason' => 'Sales order voided',
                                ]);
                            }
                        }

                        foreach ($record->salesOrderItems as $item) {
                            $exists = StockMovement::where('reference_type', get_class($record))
                                ->where('reference_id', $record->id)
                                ->where('inventory_item_id', $item->inventory_item_id)
                                ->where('type', 'sale')
                                ->exists();

                            if (! $exists) {
                                continue;
                            }

                            StockMovement::create([
                                'inventory_item_id' => $item->inventory_item_id,
                                'warehouse_id' => $record->warehouse_id,
                                'type' => 'sale_return',
                                'reference_type' => get_class($record),
                                'reference_id' => $record->id,
                                'quantity' => abs($item->baseQuantityForStockMovement()),
                                'movement_date' => now(),
                            ]);
                        }

                        $record->update(['status' => SalesOrder::STATUS_VOID]);
                    });

                    Notification::make()
                        ->title('Sales Order Voided')
                        ->body($record->order_number.' has been voided. All payments, journal entries, and inventory movements have been reversed.')
                        ->success()
                        ->send();
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Void Failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}

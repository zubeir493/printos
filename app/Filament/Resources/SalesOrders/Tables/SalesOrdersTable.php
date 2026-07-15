<?php

namespace App\Filament\Resources\SalesOrders\Tables;

use App\Enums\PaymentTransactionType;
use App\Filament\Exports\SalesOrderExporter;
use App\Filament\Support\PanelAccess;
use App\Filament\Tables\Filters\DateRangeFilter;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\Accounting\VoidPaymentJournalEntry;
use App\Services\InvoiceGeneratorService;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class SalesOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->label('Sales Order')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->partner?->name)
                    ->weight('bold')
                    ->color('primary'),
                TextColumn::make('paid_amount')
                    ->label('Payment Status')
                    ->state(fn ($record) => Money::format($record->paid_amount).'/'.Money::format($record->total))
                    ->color(fn ($record) => $record->balance > 0 ? 'warning' : 'success')
                    ->description(fn ($record) => $record->payments_count > 0
                        ? $record->payments_count.' payment(s)'
                        : 'No payments'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        SalesOrder::STATUS_SUBMITTED => 'info',
                        SalesOrder::STATUS_COMPLETED => 'success',
                        SalesOrder::STATUS_VOID => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('order_date')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                DateRangeFilter::make('order_date_range', 'order_date', 'Order date'),

                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'submitted' => 'Submitted',
                        'completed' => 'Completed',
                        'void' => 'Void',
                    ]),
                SelectFilter::make('payment_mode')
                    ->label('Payment Type')
                    ->options([
                        'cash' => 'Cash',
                        'credit' => 'Credit',
                    ]),
                SelectFilter::make('warehouse_id')
                    ->label('Warehouse')
                    ->options(Warehouse::orderBy('name')->pluck('name', 'id')->all()),
            ])
            ->defaultSort('order_date', 'desc')
            ->recordActions([
                ActionGroup::make([
                    Action::make('complete')
                        ->label('Complete Sale')
                        ->icon('heroicon-o-check-circle')
                        ->color('gray')
                        ->visible(fn ($record) => $record->status === SalesOrder::STATUS_DRAFT
                            && $record->isCashSale()
                            && PanelAccess::canManageSalesOrders())
                        ->requiresConfirmation()
                        ->modalHeading('Complete this Sales Order?')
                        ->modalDescription('This will mark the sale as completed and deduct inventory.')
                        ->action(function ($record): void {
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
                        }),
                    Action::make('submit_items')
                        ->label('Submit Items')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('gray')
                        ->visible(fn ($record) => $record->status === SalesOrder::STATUS_DRAFT
                            && $record->payment_mode === 'credit'
                            && PanelAccess::canManageSalesOrders())
                        ->requiresConfirmation()
                        ->modalHeading('Submit items for this credit sale?')
                        ->modalDescription('This will post the sale and deduct inventory. The order will only be completed after full payment is received.')
                        ->action(function ($record): void {
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
                        }),
                    Action::make('pay')
                        ->label('Receive Payment')
                        ->icon('heroicon-o-banknotes')
                        ->color('gray')
                        ->visible(
                            fn ($record) => $record->payment_mode === 'credit' &&
                                $record->status !== SalesOrder::STATUS_VOID &&
                                $record->balance > 0 &&
                                PanelAccess::canAccessFinanceSection() &&
                                in_array($record->status, [SalesOrder::STATUS_DRAFT, SalesOrder::STATUS_SUBMITTED], true)
                        )
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
                                    ->visible(fn (callable $get) => in_array($get('method'), ['bank', 'bank_transfer', 'cheque', 'check'], true))
                                    ->required(fn (callable $get) => in_array($get('method'), ['bank', 'bank_transfer', 'cheque', 'check'], true)),
                                TextInput::make('amount')
                                    ->label('Payment Amount')
                                    ->required()
                                    ->numeric()
                                    ->suffix(fn (): string => Money::suffix())
                                    ->default(fn ($record) => $record->balance)
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
                        ->action(function ($record, array $data) {
                            try {
                                DB::beginTransaction();

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
                                    'transaction_type' => PaymentTransactionType::CUSTOMER_RECEIPT->value,
                                    'amount' => $amount,
                                    'method' => $data['method'],
                                    'bank_id' => $data['bank_id'] ?? null,
                                    'reference' => $data['reference'] ?? 'Payment for '.$lockedRecord->order_number,
                                    'payable_type' => get_class($lockedRecord),
                                    'payable_id' => $lockedRecord->id,
                                ]);

                                DB::commit();

                                Notification::make()
                                    ->title('Payment Recorded')
                                    ->body(Money::format($amount).' received for '.$lockedRecord->order_number.'.')
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                DB::rollBack();

                                Notification::make()
                                    ->title('Payment Failed')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                    Action::make('invoice')
                        ->label('Invoice')
                        ->icon('heroicon-o-document-text')
                        ->color('gray')
                        ->hidden(fn ($record) => $record->invoices()->exists() || ! PanelAccess::canSeeMoneyValues() || $record->balance <= 0)
                        ->action(function ($record) {
                            try {
                                $invoiceService = app(InvoiceGeneratorService::class);
                                $result = $invoiceService->generateFromSalesOrder($record);

                                $actions = [
                                    Action::make('download')
                                        ->label('Download')
                                        ->url($invoiceService->getInvoicePath($result['filename']))
                                        ->openUrlInNewTab(),
                                ];

                                Notification::make()
                                    ->title('Invoice Generated')
                                    ->body('Invoice '.$result['invoice_data']['invoice_number'].' created successfully.')
                                    ->success()
                                    ->actions($actions)
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Invoice Action Failed')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                    Action::make('void')
                        ->label('Void')
                        ->icon('heroicon-o-x-circle')
                        ->color('gray')
                        ->visible(fn ($record) => in_array($record->status, [SalesOrder::STATUS_SUBMITTED, SalesOrder::STATUS_COMPLETED], true))
                        ->requiresConfirmation()
                        ->modalHeading('Void Sales Order')
                        ->modalDescription('Are you sure you want to void this sales order? This action cannot be undone.')
                        ->modalSubmitActionLabel('Void Order')
                        ->action(function ($record) {
                            try {
                                DB::beginTransaction();

                                $record->load(['payments', 'salesOrderItems.inventoryItem']);
                                foreach ($record->payments as $payment) {
                                    if (! $payment->voided_at) {
                                        try {
                                            app(VoidPaymentJournalEntry::class)->handle($payment, 'Sales order voided');
                                        } catch (\Exception $e) {
                                            // If no journal entry exists, just void the payment directly
                                            if (str_contains($e->getMessage(), 'No posted journal entry was found')) {
                                                $payment->update([
                                                    'voided_at' => now(),
                                                    'voided_by' => auth()->id(),
                                                    'void_reason' => 'Sales order voided',
                                                ]);
                                            } else {
                                                throw $e;
                                            }
                                        }
                                    }
                                }

                                // Return inventory items to stock
                                foreach ($record->salesOrderItems as $item) {
                                    // Prevent duplicate movements
                                    $exists = StockMovement::where('reference_type', get_class($record))
                                        ->where('reference_id', $record->id)
                                        ->where('inventory_item_id', $item->inventory_item_id)
                                        ->where('type', 'sale')
                                        ->exists();

                                    if ($exists) {
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
                                }

                                // Mark order as void
                                $record->update(['status' => 'void']);

                                DB::commit();

                                Notification::make()
                                    ->title('Sales Order Voided')
                                    ->body($record->order_number.' has been voided. All payments, journal entries, and inventory movements have been reversed.')
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                DB::rollBack();

                                Notification::make()
                                    ->title('Void Failed')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                    EditAction::make()
                        ->visible(fn ($record) => in_array($record->status, [SalesOrder::STATUS_DRAFT], true)),

                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(SalesOrderExporter::class)
                        ->visible(fn () => PanelAccess::canManageSalesOrders()),
                ]),
            ]);
    }
}

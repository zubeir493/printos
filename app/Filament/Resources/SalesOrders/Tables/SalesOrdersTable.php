<?php

namespace App\Filament\Resources\SalesOrders\Tables;

use App\Filament\Exports\SalesOrderExporter;
use App\Filament\Support\PanelAccess;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use App\Services\InvoiceGeneratorService;
use App\Services\Accounting\VoidPaymentJournalEntry;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ExportBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

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
                    ->state(fn ($record) => number_format($record->paid_amount, 2).'/'.number_format($record->total, 2).' Birr')
                    ->color(fn ($record) => $record->balance > 0 ? 'warning' : 'success')
                    ->description(fn ($record) => $record->paymentAllocations()->count() > 0
                        ? $record->paymentAllocations()->count().' payment(s)'
                        : 'No payments'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'completed' => 'success',
                        'void' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('order_date')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
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
            ->recordActions([
                Action::make('pay')
                    ->label('Recieve Payment')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn ($record) => 
                        $record->balance > 0 && 
                        PanelAccess::canAccessFinanceSection() &&
                        $record->status === 'completed'
                    )
                    ->schema([
                        \Filament\Schemas\Components\Grid::make(2)->schema([
                            \Filament\Forms\Components\Select::make('method')
                                ->label('Paid Via')
                                ->options([
                                    'cash'   => 'Cash',
                                    'bank'   => 'Bank Transfer',
                                    'cheque' => 'Cheque',
                                ])
                                ->default('cash')
                                ->required()
                                ->live(),
                            \Filament\Forms\Components\Select::make('bank_id')
                                ->label('Bank Account')
                                ->options(\App\Models\Bank::pluck('name', 'id'))
                                ->searchable()
                                ->preload()
                                ->visible(fn (callable $get) => $get('method') === 'bank')
                                ->required(fn (callable $get) => $get('method') === 'bank'),
                            TextInput::make('allocated_amount')
                                ->label('Amount to Allocate')
                                ->required()
                                ->numeric()
                                ->suffix('Birr')
                                ->default(fn ($record) => $record->balance)
                                ->helperText(fn ($record) => "Balance: {$record->balance} Birr"),
                            \Filament\Forms\Components\DatePicker::make('payment_date')
                                ->label('Payment Date')
                                ->default(now())
                                ->required(),
                            \Filament\Forms\Components\TextInput::make('reference')
                                ->label('Memo / Reference')
                                ->placeholder('Receipt number, cheque number, or short note')
                                ->maxLength(255),
                        ]),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            DB::beginTransaction();

                            $amount = (float) $data['allocated_amount'];

                            if ($amount > $record->balance) {
                                throw new \Exception("Cannot allocate more than the remaining balance of {$record->balance} Birr.");
                            }

                            $payment = \App\Models\Payment::create([
                                'partner_id'       => $record->partner_id,
                                'payment_date'     => $data['payment_date'],
                                'transaction_type' => \App\Enums\PaymentTransactionType::CUSTOMER_RECEIPT->value,
                                'amount'           => $amount,
                                'method'           => $data['method'],
                                'bank_id'          => $data['bank_id'] ?? null,
                                'reference'        => $data['reference'] ?? 'Payment for '.$record->order_number,
                            ]);

                            $payment->paymentAllocations()->create([
                                'allocatable_id' => $record->id,
                                'allocatable_type' => get_class($record),
                                'allocated_amount' => $amount,
                            ]);

                            DB::commit();

                            Notification::make()
                                ->title('Payment Recorded')
                                ->body("{$amount} Birr received for {$record->order_number}.")
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
                    ->color('primary')
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

                            // Only add email action if partner has email
                            if ($record->partner && $record->partner->email) {
                                $actions[] = Action::make('email')
                                    ->label('Email Invoice')
                                    ->icon('heroicon-o-envelope')
                                    ->action(function () use ($record, $result, $invoiceService) {
                                        $sent = $invoiceService->sendInvoiceEmail(
                                            $result,
                                            $record->partner->email
                                        );

                                        if ($sent) {
                                            // Update invoice record with email info
                                            $result['invoice']->update([
                                                'emailed_at' => now(),
                                                'email_recipient' => $record->partner->email,
                                            ]);

                                            Notification::make()
                                                ->title('Invoice Sent')
                                                ->body('Invoice emailed to '.$record->partner->email)
                                                ->success()
                                                ->send();
                                        } else {
                                            Notification::make()
                                                ->title('Email Failed')
                                                ->body('Failed to send invoice. Please check email configuration.')
                                                ->danger()
                                                ->send();
                                        }
                                    });
                            }

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
                    ->color('danger')
                    ->visible(fn ($record) => $record->status === 'completed')
                    ->requiresConfirmation()
                    ->modalHeading('Void Sales Order')
                    ->modalDescription('Are you sure you want to void this sales order? This action cannot be undone.')
                    ->modalSubmitActionLabel('Void Order')
                    ->action(function ($record) {
                        try {
                            DB::beginTransaction();
                            
                            // Void all payment allocations and reverse journal entries
                            $record->load('paymentAllocations.payment');
                            foreach ($record->paymentAllocations as $allocation) {
                                $payment = $allocation->payment;
                                if ($payment && !$payment->voided_at) {
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

                                if (!$exists) {
                                    StockMovement::create([
                                        'inventory_item_id' => $item->inventory_item_id,
                                        'warehouse_id' => $record->warehouse_id,
                                        'type' => 'sale_return',
                                        'reference_type' => get_class($record),
                                        'reference_id' => $record->id,
                                        'quantity' => abs($item->quantity),
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
                
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(SalesOrderExporter::class),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn () => PanelAccess::canManageSalesOrders()),
                    ExportBulkAction::make()
                        ->exporter(SalesOrderExporter::class)
                        ->visible(fn () => PanelAccess::canManageSalesOrders()),
                ]),
            ]);
    }
}

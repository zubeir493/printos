<?php

namespace App\Filament\Resources\PurchaseOrders\Tables;

use App\Enums\PaymentTransactionType;
use App\Filament\Exports\PurchaseOrderExporter;
use App\Filament\Support\PanelAccess;
use App\Filament\Tables\Filters\DateRangeFilter;
use App\Models\Bank;
use App\Models\Partner;
use App\Models\Payment;
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
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class PurchaseOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('po_number')
                    ->label('PO #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary')
                    ->description(fn ($record) => $record->partner?->name),
                TextColumn::make('payment_progress')
                    ->label('Payment Progress')
                    ->getStateUsing(function ($record) {
                        $total = $record->total ?? 0;
                        $paid = $record->paid_amount ?? 0;
                        if ($total == 0) {
                            return 'N/A';
                        }
                        $percentage = round(($paid / $total) * 100, 1);

                        return "{$percentage}% (".Money::format($paid).'/'.Money::format($total).')';
                    })
                    ->description(function ($record) {
                        $balance = $record->balance ?? 0;

                        return $balance > 0 ? 'Balance: '.Money::format($balance) : 'Paid in full';
                    })
                    ->color(function ($record) {
                        $total = $record->total ?? 0;
                        $paid = $record->paid_amount ?? 0;
                        if ($total == 0) {
                            return 'gray';
                        }
                        $percentage = ($paid / $total) * 100;
                        if ($percentage >= 100) {
                            return 'success';
                        }
                        if ($percentage >= 50) {
                            return 'warning';
                        }

                        return 'danger';
                    }),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'approved' => 'info',
                        'received' => 'success',
                        'cancelled' => 'danger',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'Draft',
                        'approved' => 'Approved',
                        'received' => 'Received',
                        'cancelled' => 'Cancelled',
                    }),
                TextColumn::make('order_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                DateRangeFilter::make('order_date_range', 'order_date', 'Order date'),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'approved' => 'Approved',
                        'received' => 'Received',
                        'cancelled' => 'Cancelled',
                    ]),
                SelectFilter::make('partner_id')
                    ->label('Supplier')
                    ->options(fn (): array => Partner::query()
                        ->where('is_supplier', true)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all()),
                Filter::make('unpaid')
                    ->label('Unpaid Only')
                    ->query(fn ($query) => $query->where('balance', '>', 0))
                    ->toggle(),
            ])
            ->defaultSort('order_date', 'desc')
            ->recordActions([
                ActionGroup::make([
                    Action::make('approve')
                        ->label('Approve')
                        ->icon('heroicon-o-check-circle')
                        ->color('gray')
                        ->visible(fn ($record) => $record->status === 'draft' && PanelAccess::canManagePurchaseOrders())
                        ->requiresConfirmation()
                        ->modalHeading('Approve this Purchase Order?')
                        ->modalDescription('This marks the purchase order as approved and ready for receiving.')
                        ->action(function ($record): void {
                            $record->update(['status' => 'approved']);
                            Notification::make()->title('Purchase order approved')->success()->send();
                        }),
                    Action::make('mark_received')
                        ->label('Mark as Received')
                        ->icon('heroicon-o-check-circle')
                        ->color('gray')
                        ->visible(fn ($record) => $record->status === 'approved'
                            && PanelAccess::canManagePurchaseOrders()
                            && $record->goodsReceipts()->exists())
                        ->requiresConfirmation()
                        ->modalHeading('Mark Purchase Order as Received')
                        ->modalDescription('Manually mark this purchase order as received? Use this if you want to close the PO even if quantities are not fully received.')
                        ->action(function ($record): void {
                            $record->update(['status' => 'received']);
                            Notification::make()->title('Purchase order marked as received')->success()->send();
                        }),
                    Action::make('cancel')
                        ->label('Cancel')
                        ->icon('heroicon-o-x-circle')
                        ->color('gray')
                        ->visible(fn ($record) => in_array($record->status, ['draft', 'approved']) && PanelAccess::canManagePurchaseOrders())
                        ->requiresConfirmation()
                        ->modalHeading('Cancel Purchase Order')
                        ->modalDescription('Cancel this purchase order? This action cannot be undone.')
                        ->action(function ($record): void {
                            $record->update(['status' => 'cancelled']);
                            Notification::make()->title('Purchase order cancelled')->danger()->send();
                        }),
                    Action::make('pay')
                        ->label('Pay')
                        ->icon('heroicon-o-banknotes')
                        ->color('gray')
                        ->visible(fn ($record) => $record->balance > 0 &&
                            PanelAccess::canAccessFinanceSection() &&
                            in_array($record->status, ['approved', 'received'])
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
                                    ->default('bank')
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
                                    'transaction_type' => PaymentTransactionType::SUPPLIER_PAYMENT->value,
                                    'amount' => $amount,
                                    'method' => $data['method'],
                                    'bank_id' => $data['bank_id'] ?? null,
                                    'reference' => $data['reference'] ?? 'Payment for '.$lockedRecord->po_number,
                                    'payable_type' => get_class($lockedRecord),
                                    'payable_id' => $lockedRecord->id,
                                ]);

                                DB::commit();

                                Notification::make()
                                    ->title('Payment Recorded')
                                    ->body(Money::format($amount).' paid against '.$lockedRecord->po_number.'.')
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
                    EditAction::make()
                        ->color('gray')
                        ->visible(fn ($record) => $record->status === 'draft' && PanelAccess::canManagePurchaseOrders()),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(PurchaseOrderExporter::class)
                        ->visible(fn () => PanelAccess::canManagePurchaseOrders()),
                ]),
            ]);
    }
}

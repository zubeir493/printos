<?php

namespace App\Filament\Resources\PurchaseOrders\Tables;

use App\Enums\PaymentTransactionType;
use App\Filament\Exports\PurchaseOrderExporter;
use App\Filament\Support\PanelAccess;
use App\Models\Bank;
use App\Models\Partner;
use App\Models\Payment;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ExportAction;
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
                    ->options(Partner::where('is_supplier', true)->pluck('name', 'id')->toArray()),
                Filter::make('unpaid')
                    ->label('Unpaid Only')
                    ->query(fn ($query) => $query->where('balance', '>', 0))
                    ->toggle(),
            ])
            ->defaultSort('order_date', 'desc')
            ->recordActions([
                ActionGroup::make([
                    Action::make('pay')
                        ->label('Pay')
                        ->icon('heroicon-o-banknotes')
                        ->color('success')
                        ->visible(fn ($record) => $record->balance > 0 &&
                            PanelAccess::canAccessFinanceSection() &&
                            in_array($record->status, ['approved', 'received'])
                        )
                        ->schema([
                            Grid::make(2)->schema([
                                Select::make('method')
                                    ->label('Paid Via')
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
                                    ->options(Bank::pluck('name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->visible(fn (callable $get) => $get('method') === 'bank')
                                    ->required(fn (callable $get) => $get('method') === 'bank'),
                                TextInput::make('amount')
                                    ->label('Payment Amount')
                                    ->required()
                                    ->numeric()
                                    ->suffix('Birr')
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

                                $amount = (float) $data['amount'];

                                if ($amount > $record->balance) {
                                    throw new \Exception('Cannot pay more than the remaining balance of '.Money::format($record->balance).'.');
                                }

                                Payment::create([
                                    'partner_id' => $record->partner_id,
                                    'payment_date' => $data['payment_date'],
                                    'transaction_type' => PaymentTransactionType::SUPPLIER_PAYMENT->value,
                                    'amount' => $amount,
                                    'method' => $data['method'],
                                    'bank_id' => $data['bank_id'] ?? null,
                                    'reference' => $data['reference'] ?? 'Payment for '.$record->po_number,
                                    'payable_type' => get_class($record),
                                    'payable_id' => $record->id,
                                ]);

                                DB::commit();

                                Notification::make()
                                    ->title('Payment Recorded')
                                    ->body(Money::format($amount).' paid against '.$record->po_number.'.')
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
                ]),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(PurchaseOrderExporter::class),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn () => PanelAccess::canManagePurchaseOrders()),
                    ExportBulkAction::make()
                        ->exporter(PurchaseOrderExporter::class)
                        ->visible(fn () => PanelAccess::canManagePurchaseOrders()),
                ]),
            ]);
    }
}

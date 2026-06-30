<?php

namespace App\Filament\Resources\SalesOrders\Pages;

use App\Enums\PaymentTransactionType;
use App\Filament\Resources\SalesOrders\SalesOrderResource;
use App\Filament\Support\PanelAccess;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\SalesOrder;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Support\Colors\Color;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;

class ViewSalesOrder extends ViewRecord
{
    protected static string $resource = SalesOrderResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->getRecord()->order_number;
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                EditAction::make()
                    ->visible(fn ($record) => $record->status === SalesOrder::STATUS_DRAFT)
                    ->color(Color::Indigo),
                Action::make('complete')
                    ->label('Complete Sale')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === SalesOrder::STATUS_DRAFT &&
                        $record->isCashSale() &&
                        PanelAccess::canManageSalesOrders()
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Complete this Sales Order?')
                    ->modalDescription('This will mark the sale as completed and deduct inventory.')
                    ->action(function ($record) {
                        try {
                            $record->update(['status' => 'completed']);
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
                    ->color('primary')
                    ->visible(fn ($record) => $record->status === SalesOrder::STATUS_DRAFT &&
                        $record->payment_mode === 'credit' &&
                        PanelAccess::canManageSalesOrders()
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Submit items for this credit sale?')
                    ->modalDescription('This will post the sale and deduct inventory. The order will only be completed after full payment is received.')
                    ->action(function ($record) {
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
                    ->color('success')
                    ->visible(fn ($record) => $record->payment_mode === 'credit' &&
                        $record->status !== SalesOrder::STATUS_VOID &&
                        $record->balance > 0 &&
                        PanelAccess::canAccessFinanceSection()
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
                                    'transaction_type' => PaymentTransactionType::CUSTOMER_RECEIPT->value,
                                    'amount' => $amount,
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
                    }),
            ]),
        ];
    }
}

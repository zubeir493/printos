<?php

namespace App\Filament\Resources\Payments\Schemas;

use App\Enums\PaymentTransactionType;
use App\Models\Payment;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Payment Details')
                    ->description('Choose the preset and enter the business facts. Accounting accounts are handled automatically.')
                    ->schema([
                        TextInput::make('payment_number')
                            ->label('Payment #')
                            ->default(function (): string {
                                $lastPayment = Payment::query()->latest('id')->first();
                                $lastNumber = 0;

                                if ($lastPayment && preg_match('/PAY-(\d+)/', $lastPayment->payment_number, $matches)) {
                                    $lastNumber = (int) $matches[1];
                                }

                                return 'PAY-'.str_pad($lastNumber + 1, 6, '0', STR_PAD_LEFT);
                            })
                            ->readOnly()
                            ->dehydrated(false)
                            ->required(false),
                        Select::make('transaction_type')
                            ->label('Transaction Type')
                            ->options(PaymentTransactionType::options())
                            ->default(PaymentTransactionType::CUSTOMER_RECEIPT->value)
                            ->helperText(function (callable $get) {
                                return PaymentTransactionType::tryFrom(
                                    $get('transaction_type') ?? PaymentTransactionType::CUSTOMER_RECEIPT->value
                                )?->description();
                            })
                            ->required()
                            ->live(),
                        // Single partner field — label, filter, and create form adapt to transaction type
                        Select::make('partner_id')
                            ->label(fn ($get) => $get('transaction_type') === PaymentTransactionType::SUPPLIER_PAYMENT->value
                                ? 'Supplier'
                                : 'Customer')
                            ->relationship(
                                'partner',
                                'name',
                                modifyQueryUsing: function ($query, $get) {
                                    $type = $get('transaction_type');
                                    if ($type === PaymentTransactionType::SUPPLIER_PAYMENT->value) {
                                        return $query->where('is_supplier', true);
                                    }

                                    return $query->where('is_customer', true);
                                }
                            )
                            ->visible(fn ($get) => in_array($get('transaction_type'), [
                                PaymentTransactionType::CUSTOMER_RECEIPT->value,
                                PaymentTransactionType::SUPPLIER_PAYMENT->value,
                            ]))
                            ->required(fn ($get) => in_array($get('transaction_type'), [
                                PaymentTransactionType::CUSTOMER_RECEIPT->value,
                                PaymentTransactionType::SUPPLIER_PAYMENT->value,
                            ]))
                            ->searchable()
                            ->preload()
                            ->createOptionForm(fn ($get) => $get('transaction_type') === PaymentTransactionType::SUPPLIER_PAYMENT->value
                                ? [
                                    TextInput::make('name')->required(),
                                    TextInput::make('phone')->required(),
                                    TextInput::make('email')->email(),
                                    TextInput::make('address'),
                                    Hidden::make('is_supplier')->default(true),
                                ]
                                : [
                                    TextInput::make('name')->required(),
                                    TextInput::make('phone')->required(),
                                    TextInput::make('email')->email(),
                                    TextInput::make('address'),
                                    Hidden::make('is_customer')->default(true),
                                ]),
                        TextInput::make('amount')
                            ->required()
                            ->numeric()
                            ->suffix('Birr'),
                        DatePicker::make('payment_date')
                            ->default(now())
                            ->required(),
                        Select::make('method')
                            ->label('Paid Via')
                            ->options([
                                'cash' => 'Cash',
                                'bank' => 'Bank Transfer',
                                'cheque' => 'Cheque',
                            ])
                            ->default('bank')
                            ->afterStateHydrated(function (Select $component, $record) {
                                if (! $record) {
                                    return;
                                }

                                $component->state(match ($record->method) {
                                    'bank_transfer' => 'bank',
                                    'check' => 'cheque',
                                    default => $record->method,
                                });
                            })
                            ->required()
                            ->live(),
                        Select::make('bank_id')
                            ->label('Bank Account')
                            ->relationship('bank', 'name')
                            ->searchable()
                            ->preload()
                            ->visible(fn (callable $get) => $get('method') === 'bank')
                            ->required(fn (callable $get) => $get('method') === 'bank')
                            ->helperText('Select the bank account for this payment'),
                        TextInput::make('reference')
                            ->label('Memo / Reference')
                            ->placeholder('For example: receipt number, bill number, or short note')
                            ->helperText('Use this field for later lookup and audit reference.')
                            ->maxLength(255),
                    ])
                    ->columnSpanFull()
                    ->columns(2),
            ]);
    }
}
